-- Executado pelo mysqld em cada arranque via --init-file (não apenas na inicialização do volume).
-- Garante que findoc tem ALL PRIVILEGES para que os testes paralelos possam criar
-- e eliminar as bases de dados findocprocessor_testing_test_N.
GRANT ALL PRIVILEGES ON *.* TO 'findoc'@'%';
FLUSH PRIVILEGES;
