-- Bolsa: cache partilhada de cotações e de câmbio (só o servidor escreve) e lista de acompanhamento por utilizador.

CREATE TABLE IF NOT EXISTS market_quotes (
  symbol         VARCHAR(20)   NOT NULL PRIMARY KEY,
  name           VARCHAR(120)  NULL,
  currency       CHAR(3)       NOT NULL,
  price          DECIMAL(18,6) NOT NULL,          -- na moeda original
  price_eur      DECIMAL(18,6) NOT NULL,
  prev_close_eur DECIMAL(18,6) NULL,              -- fecho anterior, para a variação do dia
  spark          TEXT          NULL,              -- JSON com os fechos do último mês (já em EUR)
  fetched_at     DATETIME      NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fx_rates (
  currency     CHAR(3)       NOT NULL PRIMARY KEY,
  eur_per_unit DECIMAL(18,8) NOT NULL,
  fetched_at   DATETIME      NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS watchlist (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  symbol     VARCHAR(20)  NOT NULL,
  created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_watch (user_id, symbol),
  CONSTRAINT fk_watch_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
