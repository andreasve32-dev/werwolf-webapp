-- Push-Subscriptions für Web Push Notifications
-- Ausführen: einmalig nach dem Einspielen dieser Datei

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    player_id  INT NOT NULL,
    endpoint   TEXT NOT NULL,
    p256dh     VARCHAR(512) DEFAULT NULL,
    auth       VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_player_endpoint (player_id, endpoint(191)),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
