# This sql file will be run when the mysql database is created
# This is because this file will be added to /mysql/docker-entrypoint-initdb.d/ inside the mysql container
# Create a new user with password
#CREATE USER 'test_user'@'%' IDENTIFIED BY 'password';

# Create database 1up_forum and give user all privlages and remote access (@'%' - is wildcard symbol for any host ip for example @'localhost', eetc)
# CREATE DATABASE IF NOT EXISTS `test_db` ;
# GRANT ALL ON `test_db`.* TO 'test_user'@'%' ;

# Delete all anonymous users
DELETE FROM mysql.user WHERE User='';

# Disable remote login as root
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

# This enables the changes to take effect
FLUSH PRIVILEGES ;
