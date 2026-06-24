-- Executado pelo MySQL apenas na primeira inicialização do volume de dados.
-- Cria a base de dados de teste dedicada (usada por `composer test:mysql`)
-- e concede acesso ao utilizador da aplicação, sem tocar na BD de dev.
CREATE DATABASE IF NOT EXISTS findocprocessor_testing
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON findocprocessor_testing.* TO 'findoc'@'%';
FLUSH PRIVILEGES;
