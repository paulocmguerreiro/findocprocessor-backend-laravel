-- Executado pelo MySQL apenas na primeira inicialização do volume de dados.
-- Cria a base de dados de teste dedicada e concede acesso global ao utilizador
-- da aplicação (necessário para o paralelo criar findocprocessor_testing_test_N).
CREATE DATABASE IF NOT EXISTS findocprocessor_testing
    CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

-- GRANT global: necessário para o paralelo (Laravel cria findocprocessor_testing_test_N)
GRANT ALL PRIVILEGES ON *.* TO 'findoc'@'%';
FLUSH PRIVILEGES;
