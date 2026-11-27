-- Atualização segura para bancos criados antes das fotos no acervo.
USE guarda_roupa_estilista;

SET @has_garment_image_path := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'garments'
      AND COLUMN_NAME = 'image_path'
);
SET @migration_sql := IF(
    @has_garment_image_path = 0,
    'ALTER TABLE garments ADD COLUMN image_path VARCHAR(255) NULL AFTER style',
    'SELECT 1'
);
PREPARE vesti_migration FROM @migration_sql;
EXECUTE vesti_migration;
DEALLOCATE PREPARE vesti_migration;

SET @has_item_image_snapshot := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'look_suggestion_items'
      AND COLUMN_NAME = 'image_path_snapshot'
);
SET @migration_sql := IF(
    @has_item_image_snapshot = 0,
    'ALTER TABLE look_suggestion_items ADD COLUMN image_path_snapshot VARCHAR(255) NULL AFTER style_snapshot',
    'SELECT 1'
);
PREPARE vesti_migration FROM @migration_sql;
EXECUTE vesti_migration;
DEALLOCATE PREPARE vesti_migration;

INSERT IGNORE INTO occasions (name, min_temp_c, max_temp_c) VALUES
    ('Trabalho', 18.0, 30.0),
    ('Jantar', 18.0, 30.0);

-- Corrige os textos existentes antes de inserir o acervo de demonstração.
UPDATE garments SET category = 'Calça' WHERE category = 'Cal?a';
UPDATE garments SET category = 'Acessório' WHERE category = 'Acess?rio';
UPDATE garments SET category = 'Calçado' WHERE category = 'Cal?ado';
UPDATE garments SET name = REPLACE(name, 'Cal?a', 'Calça') WHERE name LIKE '%Cal?a%';
UPDATE garments SET name = REPLACE(name, '?culos', 'Óculos') WHERE name LIKE '%?culos%';
UPDATE garments SET name = REPLACE(name, 'Bon?', 'Boné') WHERE name LIKE '%Bon?%';
UPDATE garments SET name = REPLACE(name, 'T?nis', 'Tênis') WHERE name LIKE '%T?nis%';
UPDATE garments SET style = 'Versátil' WHERE style = 'Vers?til';

-- Inclui as fotos enviadas no acervo do administrador de demonstração.
INSERT INTO garments
    (user_id, name, category, color, min_temp_c, max_temp_c, style, image_path)
SELECT account.id, piece.name, piece.category, piece.color,
       piece.min_temp_c, piece.max_temp_c, piece.style, piece.image_path
FROM users AS account
JOIN (
    SELECT 'Calça jeans reta azul' AS name, 'Calça' AS category,
           'Azul médio' AS color, 14.0 AS min_temp_c, 32.0 AS max_temp_c,
           'Casual' AS style, 'assets/images/garments/jeans-blue.png' AS image_path
    UNION ALL SELECT 'Calça jeans com cruzes', 'Calça', 'Off-white e preto', 14.0, 32.0,
           'Criativo', 'assets/images/garments/jeans-crosses.png'
    UNION ALL SELECT 'Moletom preto e branco', 'Moletom', 'Preto e branco', 8.0, 24.0,
           'Casual', 'assets/images/garments/sweatshirt-black-white.png'
    UNION ALL SELECT 'Calça jeans cinza rasgada', 'Calça', 'Cinza', 12.0, 30.0,
           'Criativo', 'assets/images/garments/jeans-gray-ripped.png'
    UNION ALL SELECT 'Camiseta esportiva', 'Camiseta', 'Preto e branco', 18.0, 35.0,
           'Esportivo', 'assets/images/garments/tshirt-sport.png'
    UNION ALL SELECT 'Boné jeans estampado', 'Acessório', 'Preto e cinza', 15.0, 35.0,
           'Casual', 'assets/images/garments/cap-denim.png'
    UNION ALL SELECT 'Óculos pretos', 'Acessório', 'Preto', 15.0, 35.0,
           'Esportivo', 'assets/images/garments/sunglasses-black.png'
    UNION ALL SELECT 'Tênis preto e branco', 'Calçado', 'Preto e branco', 10.0, 35.0,
           'Casual', 'assets/images/garments/sneakers-black-white.png'
    UNION ALL SELECT 'Tênis branco', 'Calçado', 'Branco', 10.0, 35.0,
           'Versátil', 'assets/images/garments/sneakers-white.png'
    UNION ALL SELECT 'Tênis estampado azul', 'Calçado', 'Azul e branco', 10.0, 35.0,
           'Criativo', 'assets/images/garments/sneakers-custom-blue.png'
    UNION ALL SELECT 'Chinelo slide branco', 'Calçado', 'Off-white', 18.0, 35.0,
           'Casual', 'assets/images/garments/slides-white.png'
    UNION ALL SELECT 'Camisa de futebol preta e vermelha', 'Camisa', 'Preto e vermelho', 15.0, 35.0,
           'Esportivo', 'assets/images/garments/jersey-red-black.png'
    UNION ALL SELECT 'Camiseta Los Angeles', 'Camiseta', 'Preto', 18.0, 35.0,
           'Streetwear', 'assets/images/garments/tshirt-los-angeles.png'
    UNION ALL SELECT 'Camiseta preta', 'Camiseta', 'Preto', 18.0, 35.0,
           'Casual', 'assets/images/garments/tshirt-black.png'
) AS piece ON 1 = 1
LEFT JOIN garments AS existing
    ON existing.user_id = account.id
   AND existing.name = piece.name
WHERE account.email = 'admin@trama.test'
  AND existing.id IS NULL;
