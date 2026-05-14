# Nexus OMS — Conceitos Fundamentais

> Guia de estudo dos conceitos de mensageria, filas e arquitetura event-driven aplicados no projeto Nexus OMS.
> Os conceitos estão ordenados do mais básico ao mais avançado — cada um usa o anterior como base.

---

## 1. Message Broker (RabbitMQ)

É o intermediário entre quem produz mensagens e quem as consome. Nenhum dos dois se conhece diretamente — eles só conhecem o broker.

A analogia mais simples é uma agência de correios: o remetente entrega o pacote na agência, e o destinatário vai lá buscar. O remetente não precisa saber onde o destinatário está, nem esperar ele estar disponível.

No Nexus OMS, a API PHP é o remetente e os workers são os destinatários. O RabbitMQ é a agência no meio.

---

## 2. Producer e Consumer

**Producer** é quem publica mensagens na fila. No projeto, é a API PHP — quando um pedido muda de estado, ela publica um evento.

**Consumer** é quem lê e processa as mensagens. No projeto, são os workers: `PaymentWorker`, `NotificationWorker`, etc. Cada um fica em loop infinito esperando mensagens chegarem na sua fila.

A separação é importante: o producer não espera o consumer terminar. Ele publica e segue em frente — isso é o que torna o sistema assíncrono.

---

## 3. Exchange, Queue e Binding

Esses três são o núcleo do RabbitMQ e costumam confundir no início.

**Queue** é a fila em si — onde as mensagens ficam armazenadas até um consumer buscá-las. No projeto: `orders.payment`, `orders.notification`, `orders.audit`, etc.

**Exchange** é o roteador — recebe a mensagem do producer e decide para qual fila (ou filas) ela vai. O producer nunca publica direto na fila, sempre publica no exchange.

**Binding** é a regra que conecta um exchange a uma fila. É você quem define: "mensagens com routing key X vão para a fila Y".

```
Producer → Exchange → (binding decide) → Queue → Consumer
```

---

## 4. Topic Exchange

É o tipo de exchange mais poderoso e o que o Nexus OMS usa. Ele roteia mensagens baseado em padrões na routing key, usando dois curingas:

- `*` substitui exatamente uma palavra
- `#` substitui zero ou mais palavras

No projeto, os eventos seguem o padrão `order.{domínio}.{ação}`:

```
order.created              → AuditWorker (order.#), NotificationWorker
order.payment.pending      → AuditWorker (order.#), PaymentWorker (order.payment.*)
order.payment.approved     → AuditWorker (order.#), PaymentWorker, NotificationWorker
order.shipped              → AuditWorker (order.#), TrackingWorker, NotificationWorker
```

O `AuditWorker` usa o binding `order.#` e por isso recebe absolutamente todos os eventos — é ele quem monta o histórico completo do pedido.

---

## 5. Event-Driven Architecture (EDA)

É um estilo arquitetural onde as partes do sistema se comunicam através de eventos, não de chamadas diretas.

Em vez de a API chamar diretamente o serviço de pagamento, ela publica um evento `order.payment.pending` e segue em frente. O `PaymentWorker` vai processar quando puder.

As vantagens são desacoplamento (cada parte não conhece as outras), escalabilidade (você escala os workers independentemente) e resiliência (se um worker cair, os eventos ficam na fila esperando).

A desvantagem é complexidade — o fluxo não é linear e fica mais difícil de debugar, o que é exatamente por que o dashboard de visualização é tão importante nesse tipo de sistema.

---

## 6. Máquina de Estados (State Machine)

O pedido no Nexus OMS só pode estar em um estado por vez, e só pode transitar para estados válidos a partir do estado atual.

```
criado → pagamento_pendente → pago → separando → enviado → entregue
```

Você não pode ir de `criado` direto para `enviado`. Nem voltar de `pago` para `criado`.

O `OrderService` é responsável por guardar essa lógica. Antes de qualquer transição, ele valida se aquela transição é permitida — e rejeita se não for. Isso previne que workers mal-comportados ou bugs coloquem o pedido em um estado inválido.

---

## 7. Idempotência

Uma operação é idempotente quando você pode executá-la múltiplas vezes e o resultado final é sempre o mesmo.

No contexto de filas, isso é crítico porque o RabbitMQ pode entregar a mesma mensagem mais de uma vez — por exemplo, se o worker processou mas travou antes de confirmar o recebimento.

O Nexus OMS resolve isso com a tabela `processed_events`. Antes de processar qualquer mensagem, o worker verifica se o `event_id` já existe nessa tabela. Se existir, ignora e confirma o recebimento sem fazer nada. Se não existir, processa e registra.

É a diferença entre cobrar o cliente duas vezes e perceber que já foi cobrado e não cobrar de novo.

---

## 8. Dead Letter Queue (DLQ)

É uma fila especial para onde vão as mensagens que falharam definitivamente — esgotaram todas as tentativas de retry.

Sem DLQ, uma mensagem que sempre falha ficaria eternamente na fila, bloqueando outras mensagens ou sendo descartada silenciosamente. Com DLQ, ela vai para um lugar seguro onde pode ser inspecionada, corrigida e reprocessada manualmente.

No Nexus OMS, após 3 falhas, o evento vai para `orders.dead`. O dashboard mostra esses eventos e permite reprocessamento manual.

---

## 9. Retry com Backoff Exponencial

Quando um worker falha ao processar uma mensagem, ele não deve tentar de novo imediatamente — pode ser que o serviço externo esteja sobrecarregado e tentativas instantâneas só pioram a situação.

Backoff exponencial significa aumentar o tempo de espera a cada tentativa:

```
Tentativa 1 falhou → espera 30s  → tenta de novo
Tentativa 2 falhou → espera 60s  → tenta de novo
Tentativa 3 falhou → espera 120s → tenta de novo
Tentativa 4 falhou → vai para DLQ
```

No RabbitMQ, isso é implementado com a fila `orders.retry` que tem um TTL (time-to-live). A mensagem fica lá pelo tempo configurado e depois volta automaticamente para a fila principal via Dead Letter Exchange.

---

## 10. CQRS (simplificado)

Command Query Responsibility Segregation — separar as operações de escrita das operações de leitura.

No Nexus OMS é aplicado de forma simples: quando você escreve (cria ou atualiza um pedido), vai para o PostgreSQL. Quando o dashboard lê para exibir, vai para o Redis.

O `AuditWorker` é responsável por manter o Redis atualizado — cada vez que processa um evento, atualiza o read model no Redis com o estado atual do pedido.

O benefício prático é que as queries do dashboard são extremamente rápidas (Redis em memória) sem impactar o banco principal que está recebendo escritas dos workers.

---

## 11. Saga Pattern (simplificado)

Saga é um padrão para gerenciar transações distribuídas — operações que envolvem múltiplos serviços e precisam de consistência.

No Nexus OMS é simplificado: o `OrderService` funciona como orquestrador. Ele conhece o fluxo completo do pedido e coordena a sequência de eventos. Quando algo falha, ele sabe o que fazer — cancelar, compensar ou retentar.

Em sistemas reais, o Saga pode ser muito mais complexo, com etapas de compensação (ex: se o pagamento foi aprovado mas o estoque falhou, estornar o pagamento). Aqui a ideia é entender o conceito de orquestração distribuída.

---

## 12. Acknowledgment (ack / nack)

Quando um consumer recebe uma mensagem do RabbitMQ, ele precisa confirmar o processamento — isso é o `ack` (acknowledgment).

Se o worker confirmar (`ack`), o RabbitMQ remove a mensagem da fila. Se o worker rejeitar (`nack`) ou cair sem confirmar, o RabbitMQ recoloca a mensagem na fila para outro worker tentar.

No Nexus OMS o `BaseWorker` faz `ack` **apenas após o processamento bem-sucedido**. Isso garante que nenhuma mensagem seja perdida mesmo que o worker trave no meio do processo.

---

## 13. Heartbeat dos Workers

É um sinal periódico que cada worker envia para o Redis dizendo "estou vivo e processando". No Nexus OMS, a cada 5 segundos o worker atualiza sua chave no Redis com um TTL de 15 segundos.

Se o worker morrer, a chave expira e o dashboard marca aquele consumer como inativo — sem precisar de nenhuma notificação explícita de morte do processo.

É o mesmo conceito de um pulsôximetro: enquanto o sinal chega, está vivo. Quando para de chegar, algo errado aconteceu.

---

## Mapa de Conceitos no Projeto

```
Nexus OMS
│
├── Infraestrutura
│   ├── RabbitMQ ............... Message Broker
│   ├── PostgreSQL ............. Banco de escrita (write side)
│   └── Redis .................. Cache / read model / heartbeat
│
├── Mensageria
│   ├── Exchange (topic) ....... Roteia eventos por padrão
│   ├── Queue .................. Armazena eventos por domínio
│   ├── Binding ................ Liga exchange → queue
│   ├── Producer ............... API PHP publica eventos
│   ├── Consumer ............... Workers processam eventos
│   └── Acknowledgment ......... Confirmação de processamento
│
├── Resiliência
│   ├── Retry + Backoff ........ Reprocessamento com espera crescente
│   ├── Dead Letter Queue ...... Fila de falhas definitivas
│   └── Idempotência ........... Previne reprocessamento duplicado
│
└── Arquitetura
    ├── Event-Driven ........... Comunicação via eventos
    ├── State Machine .......... Ciclo de vida controlado do pedido
    ├── CQRS (simples) ......... Leitura e escrita separadas
    ├── Saga (simples) ......... Orquestração de fluxo distribuído
    └── Heartbeat .............. Monitoramento de saúde dos workers
```

---

## Referências para aprofundar

- [RabbitMQ — Tutorials oficiais](https://www.rabbitmq.com/tutorials) — conceitos de exchange e routing
- [php-amqplib](https://github.com/php-amqplib/php-amqplib) — biblioteca PHP usada no projeto
- [Enterprise Integration Patterns](https://www.enterpriseintegrationpatterns.com) — referência sobre padrões de mensageria
- [Martin Fowler — Event-Driven Architecture](https://martinfowler.com/articles/201701-event-driven.html) — artigo sobre as diferentes abordagens de EDA
- [Martin Fowler — CQRS](https://martinfowler.com/bliki/CQRS.html) — explicação concisa do padrão
- [Martin Fowler — Saga](https://martinfowler.com/articles/patterns-of-distributed-systems/saga.html) — padrão de transações distribuídas
