# nexus-oms
O Nexus é um sistema de gerenciamento de pedidos criado para estudo de RabbitMQ. Cada transição de estado de um pedido gera um evento publicado no RabbitMQ, que é consumido por workers especializados. O dashboard oferece visualização em tempo real do ciclo de vida dos pedidos, fluxo de eventos e saúde dos consumers. 
