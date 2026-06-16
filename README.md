# Nexus OMS

Sistema de gerenciamento de pedidos orientado a eventos. Cada transição de estado gera um evento publicado no RabbitMQ,
consumido por workers especializados. O dashboard oferece visualização em tempo real do ciclo de vida dos pedidos, fluxo
de eventos e saúde dos consumers.
O objetivo foi construir uma aplicação onde todos os conceitos de **mensageria com RabbitMQ** pudessem ser observados na
prática — topic exchange, filas, bindings, dead letter queue, retry com backoff, workers concorrentes — tudo
centralizado e visível num único lugar.

### Escolhas técnicas

**RabbitMQ** é o núcleo do projeto. Cada transição de estado de um pedido vira um evento publicado em um *topic
exchange*, roteado para filas especializadas e consumido por workers independentes. O objetivo foi entender esse fluxo
na prática, não apenas na teoria.

**Docker** mantém todos os serviços isolados — broker, banco, cache, SMTP, workers e frontend — sem dependência de
ambiente local. Um único `docker compose up` sobe tudo pronto para uso.

**PHP puro (sem framework)** foi uma escolha deliberada para fortalecer o conhecimento na raiz da linguagem. A
complexidade de um framework não agregaria valor num projeto cujo foco é a camada de mensageria, e implementar o
pipeline HTTP, roteamento e injeção de dependências manualmente reforça o entendimento do que frameworks abstraem.

**Vue 3 + TypeScript** entrega um dashboard reativo e fluido com polling em tempo real. D3.js para os gráficos,
composables para separar lógica de dados da camada de apresentação.

> Projeto de estudo — foco em **Event-Driven Architecture**, **Topic Exchange**, **CQRS simplificado**, **Saga Pattern**
> e **Dead Letter Queue**.

---

## Telas

<img width="1898" height="937" alt="nexus-1" src="https://github.com/user-attachments/assets/a5605a7a-e833-44f9-801a-a3855b6fa695" />
<img width="1915" height="942" alt="nexus-orders" src="https://github.com/user-attachments/assets/1f1cd930-b2d8-4fa1-ba7e-0db9782ef22e" />
<img width="1912" height="939" alt="nexus-notifications-details" src="https://github.com/user-attachments/assets/4eb66810-3085-4848-a9d3-0988de40a215" />

## Diagrama de Serviços

<img width="1774" height="887" alt="nexus" src="https://github.com/user-attachments/assets/5a9aabe9-3a68-4376-8634-9eb11067438c" />

## Ciclo de Vida do Pedido

<img width="1693" height="929" alt="nexux-life-cycle" src="https://github.com/user-attachments/assets/c4e6306a-2b24-4bc7-a502-33079899f5f0" />

Cada transição publica **exatamente um evento** no RabbitMQ.

---

## Stack Tecnológica

| Camada                 | Tecnologia               | Versão           |
|------------------------|--------------------------|------------------|
| API / Backend          | PHP puro                 | 8.2+             |
| Message Broker         | RabbitMQ                 | 3.x (management) |
| Banco principal        | PostgreSQL               | 16               |
| Read model / Cache     | Redis                    | 7.x              |
| Frontend               | Vue.js 3 + Vite          | Vue 3 / Vite 5   |
| Tipagem                | TypeScript               | 5.x              |
| Estilo                 | Tailwind CSS             | 3.x              |
| Gráficos               | D3.js                    | 7.x              |
| Animação de contadores | CountUp.js               | latest           |
| Testes backend         | Pest PHP                 | 2.x              |
| Testes frontend        | Vitest + Testing Library | latest           |
| Containers             | Docker + Docker Compose  | latest           |
| E-mail local           | Mailpit                  | latest           |

---

## Portas dos Serviços

| Serviço             | Porta | URL                    |
|---------------------|-------|------------------------|
| API PHP             | 8000  | http://localhost:8000  |
| Dashboard Vue       | 5173  | http://localhost:5173  |
| RabbitMQ Management | 15672 | http://localhost:15672 |
| Mailpit UI          | 8025  | http://localhost:8025  |
| PostgreSQL          | 5432  | —                      |
| Redis               | 6379  | —                      |
| RabbitMQ AMQP       | 5672  | —                      |
| Mailpit SMTP        | 1025  | —                      |

---

## Como Rodar

### Pré-requisitos

- Docker e Docker Compose instalados
- Portas `8000`, `5173`, `5432`, `6379`, `5672`, `15672`, `1025`, `8025` livres

### 1. Clonar e subir os containers

```bash
git clone <repo-url>
cd nexus-oms

docker compose up -d --build
```

Aguarde todos os serviços ficarem `healthy`:

```bash
docker compose ps
```

### 2. Verificar a API

```bash
curl http://localhost:8000/
# {"status":"ok"}
```

### 3. Popular o dashboard com dados de exemplo

Cria pedidos via `OrderService` com publicação real no RabbitMQ e entra automaticamente no monitor de eventos. Os
workers processam em segundo plano, os contadores sobem em tempo real e os e-mails chegam no Mailpit. 20% dos pedidos já
nascem em `shipped` para que o `TrackingWorker` tenha trabalho imediato. Pressione `Ctrl+C` para encerrar o monitor.

```bash
docker compose exec api php bin/seed.php --orders=500
docker compose exec api php bin/seed.php --orders=500 --clear
docker compose exec api php bin/seed.php --orders=500 --no-shipped
```

> **`--clear` vs sem `--clear`:** com `--clear`, o banco é truncado antes de inserir — você parte do zero com exatamente
> N pedidos. Sem `--clear`, os novos pedidos são somados ao que já existe.

Flags disponíveis:

| Flag           | Padrão | Descrição                                                  |
|----------------|--------|------------------------------------------------------------|
| `--orders=N`   | `50`   | Quantidade de pedidos a criar                              |
| `--clear`      | off    | Limpa `orders`, `order_events` e Redis antes de semear     |
| `--no-shipped` | off    | Desativa os 20% de pedidos shipped criados automaticamente |

### 4. Truncar todos os dados

```bash
docker compose exec api php bin/truncate.php        # pede confirmação
docker compose exec api php bin/truncate.php --yes  # sem confirmação
```

Limpa todas as tabelas (`orders`, `order_events`, `processed_events`, `consumers_log`) e as chaves Redis
correspondentes.

### 5. Monitorar processamento em tempo real

```bash
# Apenas monitorar o que os workers estão processando
docker compose exec api php bin/watch.php

# Injetar pedidos e monitorar
docker compose exec api php bin/watch.php --orders=30
docker compose exec api php bin/watch.php --orders=50 --clear
```

Atualiza a tela a cada 2 segundos exibindo os últimos 25 eventos com código de cor:

- **Verde `✓`** — processado com sucesso
- **Vermelho `✗`** — falhou (com prévia do erro)
- **Amarelo `⋯`** — aguardando processamento

### 6. Abrir o dashboard

Acesse **http://localhost:5173**

### 7. Criar um pedido manualmente

```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -d '{
    "customer_name": "João Silva",
    "customer_email": "joao@exemplo.com",
    "idempotency_key": "meu-uuid-unico",
    "items": [
      { "product": "Tênis Nike Air", "qty": 1, "price": 459.90 }
    ]
  }'
```

O Event Feed no dashboard atualiza em até 2 segundos.

---

## Endpoints da API

### Pedidos

| Método | Rota                              | Descrição                           |
|--------|-----------------------------------|-------------------------------------|
| `POST` | `/api/orders`                     | Criar pedido                        |
| `GET`  | `/api/orders`                     | Listar (`?status=&page=&per_page=`) |
| `GET`  | `/api/orders/{id}`                | Detalhe + histórico de eventos      |
| `POST` | `/api/orders/{id}/pay`            | Simula aprovação de pagamento       |
| `POST` | `/api/orders/{id}/refuse-payment` | Simula recusa de pagamento          |
| `POST` | `/api/orders/{id}/cancel`         | Cancelar pedido                     |
| `POST` | `/api/orders/{id}/advance`        | Avança para próximo estado          |

### Dashboard

| Método | Rota                            | Descrição                               |
|--------|---------------------------------|-----------------------------------------|
| `GET`  | `/api/dashboard/stats`          | Totais por status + eventos + consumers |
| `GET`  | `/api/dashboard/throughput`     | Pedidos por minuto (última hora)        |
| `GET`  | `/api/dashboard/funnel`         | Contagem por estado                     |
| `GET`  | `/api/dashboard/consumers`      | Workers ativos (via Redis)              |
| `GET`  | `/api/dashboard/events/feed`    | Últimos N eventos (`?limit=50`)         |
| `GET`  | `/api/dashboard/events/by-type` | Agrupado por tipo de evento             |
| `GET`  | `/api/dashboard/queues`         | Status das filas (RabbitMQ API)         |

---

## Estrutura do Projeto

```
nexus-oms/
├── api/                          # Backend PHP 8.2
│   ├── bin/
│   │   ├── run-worker.php        # Dispatcher de workers
│   │   ├── seed.php              # Seeder de pedidos (via RabbitMQ)
│   │   ├── watch.php             # Monitor em tempo real do event stream
│   │   └── truncate.php          # Limpa todas as tabelas e chaves Redis
│   ├── src/
│   │   ├── Controllers/          # OrderController, DashboardController
│   │   ├── Services/             # OrderService, EventPublisher, HeartbeatService
│   │   ├── Workers/              # BaseWorker + 6 workers especializados
│   │   ├── Repositories/         # Order, Event, ReadModel
│   │   ├── Events/               # OrderEvent, EventFactory
│   │   ├── Mail/                 # SmtpMailer, MailerInterface
│   │   ├── Http/                 # Request, Response, Pipeline, Router
│   │   └── Middleware/           # CorsMiddleware, JsonMiddleware
│   ├── database/migrations/      # 003 migrations SQL
│   └── tests/                    # Pest PHP — Unit + Feature + Infrastructure
│
├── dashboard/                    # Frontend Vue 3 + TypeScript
│   └── src/
│       ├── components/
│       │   ├── charts/           # FunnelChart, ThroughputChart, EventFlowChart (D3)
│       │   ├── orders/           # OrderTable, OrderTimeline, OrderPipeline, StateNode
│       │   ├── consumers/        # ConsumerGrid (sparklines D3)
│       │   └── layout/           # AppLayout, Sidebar
│       ├── views/                # Dashboard, Orders, OrderDetail, Consumers, Events
│       ├── composables/          # useStats, useOrders, useConsumers, useEventFeed, usePolling
│       ├── services/             # api.ts
│       └── types/                # index.ts
│
├── docs/
│   └── IMPLEMENTATION_PLAN.md    # Plano TDD por fases (8 fases)
├── docker-compose.yml
└── SPECS.md                      # Especificação completa do sistema
```

---

## Rodando os Testes

### Backend (Pest PHP)

```bash
# Dentro do container
docker compose exec api ./vendor/bin/pest

# Apenas testes unitários
docker compose exec api ./vendor/bin/pest tests/Unit/

# Apenas testes de feature (requer DB rodando)
docker compose exec api ./vendor/bin/pest tests/Feature/
```

### Frontend (Vitest)

```bash
cd dashboard

npm run test          # modo watch
npm run test -- --run  # execução única
npm run build         # build de produção (valida TypeScript)
```

---

## Variáveis de Ambiente

Copie `api/.env` e ajuste conforme necessário. As principais:

```env
DB_HOST=postgres
DB_DATABASE=oms
DB_USERNAME=user
DB_PASSWORD=secret

RABBITMQ_HOST=rabbitmq
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest

REDIS_HOST=redis

MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM=noreply@oms.local

WORKER_TYPE=PaymentWorker   # usado pelo run-worker.php
WORKER_ID=payment-worker-1
HEARTBEAT_INTERVAL=5
MAX_ATTEMPTS=3
```

---

## Conceitos Implementados

| Conceito                   | Onde                                                        |
|----------------------------|-------------------------------------------------------------|
| **Topic Exchange**         | `EventPublisher` — roteamento por `order.*`                 |
| **Event-Driven**           | Toda transição de estado publica um evento                  |
| **Workers especializados** | 6 workers, 1 por domínio de responsabilidade                |
| **Idempotência**           | `processed_events` impede reprocessamento                   |
| **Saga Pattern**           | `OrderService` orquestra o ciclo de vida                    |
| **Dead Letter Queue**      | `orders.dead` após 3 tentativas                             |
| **Retry com backoff**      | 30s → 60s → 120s por mensagem                               |
| **CQRS simplificado**      | Escrita via API + leitura via Redis (`ReadModelRepository`) |
| **Heartbeat**              | Workers registram presença no Redis a cada 5s               |
