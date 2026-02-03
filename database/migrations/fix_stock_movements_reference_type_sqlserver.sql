-- Script para adicionar 'termo_responsabilidade' ao reference_type em stock_movements (SQL Server)
-- Execute manualmente se a migration falhar.
-- O nome da constraint pode variar (ex: CK__stock_mov__refer__68536ACF). Ajuste o DROP se necessário.

-- 1. Descobrir o nome da constraint (execute e use o resultado no DROP abaixo):
SELECT name, OBJECT_DEFINITION(object_id) AS definition
FROM sys.check_constraints
WHERE parent_object_id = OBJECT_ID('stock_movements');

-- 2. Remover a constraint antiga (substitua CK__stock_mov__refer__68536ACF pelo nome real se diferente):
ALTER TABLE [stock_movements] DROP CONSTRAINT [CK__stock_mov__refer__68536ACF];

-- 3. Criar nova constraint incluindo 'termo_responsabilidade':
ALTER TABLE [stock_movements] ADD CONSTRAINT [CK_stock_movements_reference_type] 
CHECK ([reference_type] IN ('compra', 'solicitacao', 'ajuste_manual', 'transferencia', 'outro', 'termo_responsabilidade') OR [reference_type] IS NULL);
