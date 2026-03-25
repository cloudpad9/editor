## WARNINGS
* Chưa lưu file được
    * Hiện tại nếu chạy thông qua docker thì chưa kết nối được SSH bằng private file. Do đó, workaround là chmod hoặc chown những cái cần ghi

## Quick start

### Chạy docker

```
cd /space1/docker/almalinux-9
docker-compose down
docker-compose build
docker-compose up -d\
docker ps
docker-compose logs php
docker-compose exec php bash
```

## Cấu hình repositories và users

0. Cấu hình SSH private file trong tập tin `.env`
```
SSH_PORT=22
SSH_RSA_PRIVATE_FILE=/path/to/id_rsa
SSH_RSA_USERNAME=<ten_user_tao_ssh_key>
SSH_RSA_PASSPHRASE=
```
Lưu ý: đường dẫn đến file id_rsa phải đọc được từ PHP docker container

Tạo id_rsa bằng lệnh:

```
ssh-keygen -t rsa -b 4096
```

Log vào PHP docker để chmod cho phép đọc file này, ví dụ:

```
chown www-data:www-data /var/www/default/editor/.ssh/id_rsa
```

1. Cấu hình repositories
```
nano repositories.conf.php
```

2. Cấu hình users

```
nano users.conf.php
```

## Chạy composer

```
docker-compose exec php bash -c "cd /var/www/default/editor && composer update && composer install"
docker-compose exec php bash -c "cd /var/www/default/editor && composer update && composer require <ten_thu_vien>"
```
