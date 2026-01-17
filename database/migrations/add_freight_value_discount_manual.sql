-- Script SQL para adicionar as colunas freight_value e discount manualmente
-- Execute este script diretamente no SQL Server se a migration não funcionar

-- Verificar se as colunas já existem antes de adicionar
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('purchase_quote_suppliers') AND name = 'freight_value')
BEGIN
    ALTER TABLE purchase_quote_suppliers
    ADD freight_value DECIMAL(15, 2) NULL;
    PRINT 'Coluna freight_value adicionada com sucesso.';
END
ELSE
BEGIN
    PRINT 'Coluna freight_value já existe.';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('purchase_quote_suppliers') AND name = 'discount')
BEGIN
    ALTER TABLE purchase_quote_suppliers
    ADD discount DECIMAL(15, 2) NULL;
    PRINT 'Coluna discount adicionada com sucesso.';
END
ELSE
BEGIN
    PRINT 'Coluna discount já existe.';
END

