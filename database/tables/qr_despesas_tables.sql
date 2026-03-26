-- Tabelas para funcionalidade QR Code de despesas

-- Cache de NIF → Nome da empresa (lookup automático)
CREATE TABLE IF NOT EXISTS nif_empresas_cache (
    nif           VARCHAR(9)    NOT NULL,
    nome_empresa  VARCHAR(255)  DEFAULT NULL,
    fonte         VARCHAR(20)   DEFAULT NULL,
    criado_em     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (nif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registo de despesas importadas via QR Code
CREATE TABLE IF NOT EXISTS qr_despesas_import (
    id              INT(11)       NOT NULL AUTO_INCREMENT,
    id_utilizador   INT(11)       NOT NULL,
    id_despesa      INT(11)       DEFAULT NULL,
    qr_raw          TEXT          DEFAULT NULL,
    nif_emitente    VARCHAR(20)   DEFAULT NULL,
    data_fatura     DATE          DEFAULT NULL,
    total           DECIMAL(10,2) DEFAULT NULL,
    data_importacao TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_utilizador (id_utilizador),
    KEY idx_despesa (id_despesa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categorias para auto-detecção
CREATE TABLE IF NOT EXISTS categorias_auto (
    id          INT(11)       NOT NULL AUTO_INCREMENT,
    nif         VARCHAR(9)    NOT NULL,
    categoria   VARCHAR(100)  NOT NULL,
    descricao   VARCHAR(255)  DEFAULT NULL,
    criado_em   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_nif (nif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;