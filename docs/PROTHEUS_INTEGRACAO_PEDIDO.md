# Integração Pedido de Compra – Leitura direta para Protheus (ADVPL)

O Protheus lê as tabelas do sistema de pedido **diretamente** no banco SQL Server. Não há API envolvida.

## Conexão com o banco do sistema_pedido

- **SGBD**: SQL Server
- **Banco**: o mesmo usado pela aplicação Laravel (variável `DB_DATABASE` no `.env` do sistema_pedido).
- **Servidor / porta**: `DB_HOST` e `DB_PORT` (ex.: `127.0.0.1`, `1433`).
- **Usuário / senha**: `DB_USERNAME` e `DB_PASSWORD`.

A rotina ADVPL deve usar conexão OLE/ADO (ou equivalente) apontando para esse servidor e banco.

## Tabelas e views utilizadas

### 1. Cabeçalho do pedido – view `vw_protheus_purchase_order_export`

Uma linha por pedido. Consultar por `purchase_order_id` ou `order_number`.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| purchase_order_id | int | ID do pedido (PK) |
| order_number | varchar(50) | Número do pedido (ex.: PED-000001) |
| order_date | date | Data do pedido |
| expected_delivery_date | date | Data prevista de entrega (pode ser NULL) |
| supplier_code | varchar(60) | Código do fornecedor (Protheus) |
| supplier_name | varchar | Nome do fornecedor |
| supplier_document | varchar(20) | CNPJ/CPF |
| vendor_name | varchar | Nome do vendedor |
| vendor_phone | varchar(50) | Telefone do vendedor |
| vendor_email | varchar | E-mail do vendedor |
| proposal_number | varchar(100) | Número da proposta |
| total_amount | decimal(15,2) | Valor total do pedido |
| observation | text | Observações |
| company_id | int | ID da empresa no sistema |
| protheus_order_number | varchar(60) | Número do pedido no Protheus (preenchido após exportação) |
| protheus_exported_at | datetime | Data/hora da exportação (preenchido após exportação) |
| payment_condition_code | varchar | Condição de pagamento (Protheus) |
| payment_condition_description | varchar | Descrição da condição |
| freight_type | varchar(10) | Tipo de frete |
| nature_operation_code | varchar(20) | Código da natureza de operação |
| nature_operation_cfop | varchar(10) | CFOP da natureza |
| main_cost_center_code | varchar | Centro de custo principal da cotação |
| main_cost_center_description | varchar | Descrição do centro de custo principal |

**Exemplo de consulta por ID do pedido:**

```sql
SELECT * FROM vw_protheus_purchase_order_export WHERE purchase_order_id = ?
```

**Exemplo de consulta por número do pedido:**

```sql
SELECT * FROM vw_protheus_purchase_order_export WHERE order_number = ?
```

### 2. Itens do pedido – view `vw_protheus_purchase_order_export_items`

Várias linhas por pedido. Filtrar por `purchase_order_id`.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| purchase_order_item_id | int | ID do item do pedido |
| purchase_order_id | int | ID do pedido |
| purchase_quote_item_id | int | ID do item da cotação |
| product_code | varchar(100) | Código do produto (Protheus) |
| product_description | varchar | Descrição do produto |
| quantity | decimal(15,4) | Quantidade |
| unit | varchar(20) | Unidade (ex.: UN) |
| unit_price | decimal(15,4) | Preço unitário |
| total_price | decimal(15,4) | Preço total do item |
| ipi | decimal(8,4) | IPI (pode ser NULL) |
| icms | decimal(8,4) | ICMS (pode ser NULL) |
| final_cost | decimal(15,4) | Custo final (pode ser NULL) |
| item_observation | text | Observação do item |
| cost_center_code | varchar | Centro de custo (Protheus) |
| cost_center_description | varchar | Descrição do centro de custo |
| tes_code | varchar(20) | Código TES |
| tes_description | varchar | Descrição TES |
| cfop_code | varchar(10) | CFOP |

**Exemplo de consulta dos itens de um pedido:**

```sql
SELECT * FROM vw_protheus_purchase_order_export_items
WHERE purchase_order_id = ?
ORDER BY purchase_order_item_id
```

## Atualização após exportação (opcional)

Se a rotina ADVPL gravar o pedido no Protheus (SC5/SC6) com sucesso, pode atualizar o sistema com o número gerado no Protheus e a data de exportação:

**Tabela:** `purchase_orders`

**Campos a atualizar:**

- `protheus_order_number`: número do pedido gerado no Protheus (ex.: número do SC5).
- `protheus_exported_at`: data e hora da exportação (formato compatível com SQL Server: `YYYY-MM-DD HH:MI:SS`).

**Exemplo de UPDATE:**

```sql
UPDATE purchase_orders
SET protheus_order_number = ?,
    protheus_exported_at = ?
WHERE id = ?
```

## Fluxo sugerido na rotina ADVPL

1. Receber parâmetro: `purchase_orders.id` (inteiro) ou `order_number` (ex.: PED-000001).
2. Conectar ao SQL Server do sistema_pedido.
3. Buscar uma linha em `vw_protheus_purchase_order_export` pelo `purchase_order_id` ou `order_number`.
4. Se não encontrar linha: retornar erro "Pedido não encontrado".
5. Validar: `supplier_code` e `payment_condition_code` preenchidos.
6. Buscar linhas em `vw_protheus_purchase_order_export_items` onde `purchase_order_id` = ID do pedido.
7. Se não houver itens: retornar erro "Pedido sem itens".
8. Validar itens: `product_code`, `cost_center_code`, `tes_code`, `cfop_code` conforme regras do Protheus.
9. Gerar número do pedido no Protheus (SC7 – Pedido de Compra).
10. Incluir pedido no Protheus (tabela SC7) via MsExecAuto MATA120 ou rotina equivalente do módulo de Compras.
11. Em caso de sucesso: executar UPDATE em `purchase_orders` com `protheus_order_number` e `protheus_exported_at`.
12. Em caso de falha: rollback e retornar mensagem clara.

A rotina de exemplo está em `protheus/SISPPED01.prw` (User Function U_SISPPED). Ajuste os nomes dos campos SC7 (C7_FORNECE, C7_COND, C7_CF, C7_MEMO etc.) e o programa ExecAuto (MATA120) conforme a versão do seu Protheus.

## Nome do banco e servidor

Configure na rotina ADVPL (arquivo INI ou variáveis de ambiente) os mesmos valores usados pelo sistema_pedido:

- **Servidor:** valor de `DB_HOST` (ex.: `127.0.0.1` ou nome do servidor).
- **Porta:** valor de `DB_PORT` (ex.: `1433`).
- **Banco:** valor de `DB_DATABASE`.
- **Usuário:** valor de `DB_USERNAME`.
- **Senha:** valor de `DB_PASSWORD`.

Exemplo de string de conexão OLE DB para SQL Server:

```
Provider=SQLOLEDB;Data Source=<servidor>,<porta>;Initial Catalog=<banco>;User ID=<usuario>;Password=<senha>;
```

(Adapte conforme driver e sintaxe usados no seu ambiente ADVPL.)
