run-mysql-tests:
	env DB_CONNECTION=mysql DB_HOST=${MYSQL_HOST} DB_PORT=${MYSQL_PORT} DB_DATABASE=${MYSQL_DATABASE} DB_USERNAME=${MYSQL_USERNAME} DB_PASSWORD=${MYSQL_PASSWORD} ./vendor/bin/phpunit

run-pgsql-tests:
	env DB_CONNECTION=pgsql DB_HOST=${POSTGRESQL_HOST} DB_PORT=${POSTGRESQL_PORT} DB_DATABASE=${POSTGRESQL_DATABASE} DB_USERNAME=${POSTGRESQL_USERNAME} DB_PASSWORD=${POSTGRESQL_PASSWORD} ./vendor/bin/phpunit

run-sqlite-tests:
	env DB_CONNECTION=sqlite ./vendor/bin/phpunit

run-tests:
	$(MAKE) run-mysql-tests && $(MAKE) run-pgsql-tests && $(MAKE) run-sqlite-tests
