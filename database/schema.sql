-- Banco de dados do Vestí.
CREATE DATABASE IF NOT EXISTS guarda_roupa_estilista
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE guarda_roupa_estilista;

-- Usuários comuns e administradores.
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(191) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    preferred_style VARCHAR(60) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- Ocasiões configuradas pela área administrativa.
CREATE TABLE IF NOT EXISTS occasions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    min_temp_c DECIMAL(4,1) NOT NULL,
    max_temp_c DECIMAL(4,1) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_occasions_name (name),
    CONSTRAINT chk_occasions_temperature_range
        CHECK (min_temp_c <= max_temp_c)
) ENGINE=InnoDB;

-- Ocasiões iniciais usadas no acervo e no painel demonstrativo.
INSERT IGNORE INTO occasions (name, min_temp_c, max_temp_c) VALUES
    ('Trabalho', 18.0, 30.0),
    ('Jantar', 18.0, 30.0);

-- Peças cadastradas no acervo de cada usuário.
CREATE TABLE IF NOT EXISTS garments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(60) NOT NULL,
    color VARCHAR(40) NOT NULL,
    min_temp_c DECIMAL(4,1) NOT NULL,
    max_temp_c DECIMAL(4,1) NOT NULL,
    style VARCHAR(60) NOT NULL,
    image_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_garments_user (user_id),
    KEY idx_garments_temperature (min_temp_c, max_temp_c),
    CONSTRAINT chk_garments_temperature_range
        CHECK (min_temp_c <= max_temp_c),
    CONSTRAINT fk_garments_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Registro das condições usadas para gerar cada sugestão.
CREATE TABLE IF NOT EXISTS look_suggestions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    occasion_id BIGINT UNSIGNED NULL,
    occasion_name_snapshot VARCHAR(80) NOT NULL,
    temperature_c DECIMAL(4,1) NOT NULL,
    humidity_pct DECIMAL(5,2) NULL,
    day_period ENUM(
        'madrugada',
        'manha',
        'tarde',
        'noite',
        'nao_informado'
    ) NOT NULL DEFAULT 'nao_informado',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_suggestions_user_date (user_id, created_at),
    KEY idx_suggestions_occasion (occasion_id),
    CONSTRAINT chk_suggestions_humidity
        CHECK (
            humidity_pct IS NULL
            OR (humidity_pct >= 0 AND humidity_pct <= 100)
        ),
    CONSTRAINT fk_suggestions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_suggestions_occasion
        FOREIGN KEY (occasion_id) REFERENCES occasions (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Itens dos looks com resumo para preservar o histórico do usuário.
CREATE TABLE IF NOT EXISTS look_suggestion_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    suggestion_id BIGINT UNSIGNED NOT NULL,
    garment_id BIGINT UNSIGNED NULL,
    position TINYINT UNSIGNED NOT NULL,
    name_snapshot VARCHAR(100) NOT NULL,
    category_snapshot VARCHAR(60) NOT NULL,
    color_snapshot VARCHAR(40) NOT NULL,
    style_snapshot VARCHAR(60) NOT NULL,
    image_path_snapshot VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_suggestion_position (suggestion_id, position),
    KEY idx_suggestion_items_garment (garment_id),
    CONSTRAINT fk_suggestion_items_suggestion
        FOREIGN KEY (suggestion_id) REFERENCES look_suggestions (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_suggestion_items_garment
        FOREIGN KEY (garment_id) REFERENCES garments (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Tokens de redefinição: somente o hash do token é armazenado.
CREATE TABLE IF NOT EXISTS password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_resets_token (token_hash),
    KEY idx_password_resets_user_expiry (user_id, expires_at),
    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Conta administrativa inicial para desenvolvimento local.
-- A senha é verificada pelo PHP com password_verify().
INSERT IGNORE INTO users (
    full_name,
    email,
    password_hash,
    preferred_style,
    role
) VALUES (
    'Administrador Trama',
    'admin@trama.test',
    '$2y$10$UUFcwyOG5A0MVJsqlhoXmOha1ppfiTU7FUmuX19uZ70lo.HSQO8/G',
    'Clássico contemporâneo',
    'admin'
);

-- Peças de demonstração do acervo administrativo.
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
WHERE account.email = 'admin@trama.test'
  AND NOT EXISTS (
      SELECT 1 FROM garments AS existing
      WHERE existing.user_id = account.id AND existing.name = piece.name
  );
