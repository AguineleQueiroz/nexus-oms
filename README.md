# Nexus OMS

Sistema de gerenciamento de pedidos orientado a eventos. Cada transição de estado gera um evento publicado no RabbitMQ, consumido por workers especializados. O dashboard oferece visualização em tempo real do ciclo de vida dos pedidos, fluxo de eventos e saúde dos consumers.
O objetivo foi construir uma aplicação onde todos os conceitos de **mensageria com RabbitMQ** pudessem ser observados na prática — topic exchange, filas, bindings, dead letter queue, retry com backoff, workers concorrentes — tudo centralizado e visível num único lugar.

### Escolhas técnicas

**RabbitMQ** é o núcleo do projeto. Cada transição de estado de um pedido vira um evento publicado em um *topic exchange*, roteado para filas especializadas e consumido por workers independentes. O objetivo foi entender esse fluxo na prática, não apenas na teoria.

**Docker** mantém todos os serviços isolados — broker, banco, cache, SMTP, workers e frontend — sem dependência de ambiente local. Um único `docker compose up` sobe tudo pronto para uso.

**PHP puro (sem framework)** foi uma escolha deliberada para fortalecer o conhecimento na raiz da linguagem. A complexidade de um framework não agregaria valor num projeto cujo foco é a camada de mensageria, e implementar o pipeline HTTP, roteamento e injeção de dependências manualmente reforça o entendimento do que frameworks abstraem.

**Vue 3 + TypeScript** entrega um dashboard reativo e fluido com polling em tempo real. D3.js para os gráficos, composables para separar lógica de dados da camada de apresentação.

> Projeto de estudo — foco em **Event-Driven Architecture**, **Topic Exchange**, **CQRS simplificado**, **Saga Pattern** e **Dead Letter Queue**.

---

## Diagrama de Serviços

<div align="center">

<!--
  Diagrama de arquitetura do Nexus OMS
  Renderizado como HTML inline para visualização no GitHub
-->

<table>
  <thead>
    <tr>
      <th colspan="5" align="center">
        <strong>Nexus OMS — Arquitetura de Serviços</strong>
      </th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td align="center" colspan="5">
        <br>
        <strong>[ Cliente / Browser ]</strong><br>
        <code>:5173</code>
        <br><br>
        ↕ HTTP polling (2–5s)
        <br><br>
      </td>
    </tr>
    <tr>
      <td align="center" valign="top" width="20%">
        <strong>Dashboard</strong><br>
        <sub>Vue 3 + D3.js</sub><br>
        <code>:5173</code>
      </td>
      <td align="center" valign="middle">→→→</td>
      <td align="center" valign="top" width="20%">
        <strong>API PHP</strong><br>
        <sub>PHP 8.2 puro</sub><br>
        <code>:8000</code>
      </td>
      <td align="center" valign="middle">→→→</td>
      <td align="center" valign="top" width="20%">
        <strong>PostgreSQL</strong><br>
        <sub>Banco principal</sub><br>
        <code>:5432</code>
      </td>
    </tr>
    <tr>
      <td colspan="5" align="center"><br>↕ Publica eventos<br><br></td>
    </tr>
    <tr>
      <td colspan="5" align="center">
        <strong>RabbitMQ — Exchange <code>orders</code> (topic)</strong><br>
        <code>:5672</code> &nbsp;|&nbsp; Management <code>:15672</code>
      </td>
    </tr>
    <tr>
      <td colspan="5" align="center"><br>↕ Consome filas<br><br></td>
    </tr>
    <tr>
      <td align="center" valign="top">
        <strong>PaymentWorker</strong><br>
        <sub><code>orders.payment</code></sub><br>
        <sub>70% aprovação</sub>
      </td>
      <td align="center" valign="top">
        <strong>AuditWorker</strong><br>
        <sub><code>orders.audit</code></sub><br>
        <sub>Event sourcing</sub>
      </td>
      <td align="center" valign="top">
        <strong>NotificationWorker</strong><br>
        <sub><code>orders.notification</code></sub><br>
        <sub>Email via SMTP</sub>
      </td>
      <td align="center" valign="top">
        <strong>FulfillmentWorker</strong><br>
        <sub><code>orders.fulfillment</code></sub><br>
        <sub>Separação</sub>
      </td>
      <td align="center" valign="top">
        <strong>Inventory / Tracking</strong><br>
        <sub><code>orders.inventory</code></sub><br>
        <sub><code>orders.tracking</code></sub>
      </td>
    </tr>
    <tr>
      <td colspan="5" align="center"><br>↕ Heartbeat &amp; snapshots<br><br></td>
    </tr>
    <tr>
      <td align="center" colspan="2" valign="top">
        <strong>Redis</strong><br>
        <sub>Read model + Heartbeat</sub><br>
        <code>:6379</code>
      </td>
      <td align="center" colspan="3" valign="top">
        <strong>Mailpit</strong><br>
        <sub>SMTP local + UI</sub><br>
        <code>:1025</code> &nbsp;|&nbsp; UI <code>:8025</code>
      </td>
    </tr>
  </tbody>
</table>

</div>

---

## Ciclo de Vida do Pedido

```
criado
  └─► pagamento_pendente
          ├─► [recusado] ──► pagamento_recusado ──► (fim)
          └─► pago
                └─► separando
                      └─► enviado
                            └─► entregue

[qualquer estado antes de enviado] ──► cancelado
```

Cada transição publica **exatamente um evento** no RabbitMQ.

---

## Stack Tecnológica

| Camada | Tecnologia | Versão |
|---|---|---|
| API / Backend | PHP puro | 8.2+ |
| Message Broker | RabbitMQ | 3.x (management) |
| Banco principal | PostgreSQL | 16 |
| Read model / Cache | Redis | 7.x |
| Frontend | Vue.js 3 + Vite | Vue 3 / Vite 5 |
| Tipagem | TypeScript | 5.x |
| Estilo | Tailwind CSS | 3.x |
| Gráficos | D3.js | 7.x |
| Animação de contadores | CountUp.js | latest |
| Testes backend | Pest PHP | 2.x |
| Testes frontend | Vitest + Testing Library | latest |
| Containers | Docker + Docker Compose | latest |
| E-mail local | Mailpit | latest |

---

## Portas dos Serviços

| Serviço | Porta | URL |
|---|---|---|
| API PHP | 8000 | http://localhost:8000 |
| Dashboard Vue | 5173 | http://localhost:5173 |
| RabbitMQ Management | 15672 | http://localhost:15672 |
| Mailpit UI | 8025 | http://localhost:8025 |
| PostgreSQL | 5432 | — |
| Redis | 6379 | — |
| RabbitMQ AMQP | 5672 | — |
| Mailpit SMTP | 1025 | — |

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

**A flag Live (`--live`)** — cria pedidos via `OrderService` com publicação real no RabbitMQ. Os workers processam as mensagens em segundo plano, os contadores do dashboard sobem em tempo real e os emails chegam no Mailpit.

```bash
# Live (via RabbitMQ — workers processam em tempo real)
docker compose exec api php bin/seed.php --orders=500 --live
docker compose exec api php bin/seed.php --orders=1000 --live --clear
```

> **`--clear` vs sem `--clear`:** com `--clear`, o banco é truncado antes de inserir — você parte do zero com exatamente N pedidos. Sem `--clear`, os novos pedidos são somados ao que já existe no banco.

Todas as flags disponíveis:

| Flag | Descrição |
|---|---|
| `--live` | Publica via RabbitMQ; workers processam em tempo real |
| `--clear` | Limpa `orders`, `order_events` e Redis antes de semear |

### 4. Abrir o dashboard

Acesse **http://localhost:5173**

### 5. Criar um pedido manualmente

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

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/api/orders` | Criar pedido |
| `GET` | `/api/orders` | Listar (`?status=&page=&per_page=`) |
| `GET` | `/api/orders/{id}` | Detalhe + histórico de eventos |
| `POST` | `/api/orders/{id}/pay` | Simula aprovação de pagamento |
| `POST` | `/api/orders/{id}/refuse-payment` | Simula recusa de pagamento |
| `POST` | `/api/orders/{id}/cancel` | Cancelar pedido |
| `POST` | `/api/orders/{id}/advance` | Avança para próximo estado |

### Dashboard

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/api/dashboard/stats` | Totais por status + eventos + consumers |
| `GET` | `/api/dashboard/throughput` | Pedidos por minuto (última hora) |
| `GET` | `/api/dashboard/funnel` | Contagem por estado |
| `GET` | `/api/dashboard/consumers` | Workers ativos (via Redis) |
| `GET` | `/api/dashboard/events/feed` | Últimos N eventos (`?limit=50`) |
| `GET` | `/api/dashboard/events/by-type` | Agrupado por tipo de evento |
| `GET` | `/api/dashboard/queues` | Status das filas (RabbitMQ API) |

---

## Estrutura do Projeto

```
nexus-oms/
├── api/                          # Backend PHP 8.2
│   ├── bin/
│   │   ├── run-worker.php        # Dispatcher de workers
│   │   └── seed.php              # Seeder de pedidos
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

| Conceito | Onde |
|---|---|
| **Topic Exchange** | `EventPublisher` — roteamento por `order.*` |
| **Event-Driven** | Toda transição de estado publica um evento |
| **Workers especializados** | 6 workers, 1 por domínio de responsabilidade |
| **Idempotência** | `processed_events` impede reprocessamento |
| **Saga Pattern** | `OrderService` orquestra o ciclo de vida |
| **Dead Letter Queue** | `orders.dead` após 3 tentativas |
| **Retry com backoff** | 30s → 60s → 120s por mensagem |
| **CQRS simplificado** | Escrita via API + leitura via Redis (`ReadModelRepository`) |
| **Heartbeat** | Workers registram presença no Redis a cada 5s |
