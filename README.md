# Symfony Docker Project

## Requisitos
- Docker
- Docker Compose

## Instalación

1. Clonar el repositorio:
```git clone <tu-repositorio>```

2. Copiar el archivo .env:
```cp .env.example .env```

3. Construir los contenedores:
```docker-compose build```

4. Iniciar los contenedores:
```docker-compose up -d```

5. Instalar Symfony:
```docker-compose exec php composer create-project symfony/skeleton:"7.2.3" .```

6. Establecer permisos:
```docker-compose exec php chown -R www-data:www-data var```

## Uso

- Acceder a la aplicación: http://localhost
- PHPMyAdmin: http://localhost:8080
- MySQL: localhost:3306

## Comandos útiles

- Entrar al contenedor PHP:
```docker-compose exec php bash```

- Ejecutar comandos Symfony:
```docker-compose exec php bin/console```

- Ver logs:
```docker-compose logs -f```