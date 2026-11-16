#!/bin/bash
set -e

# ==============================================================================
# Script de Despliegue Automático - Clients Factory (Producción)
# ==============================================================================

PROJECT_DIR="/var/www/clients-factory"
CONTAINER_NAME="clients-factory-app-1"
BRANCH="${1:-main}"

echo "=================================================="
echo "🚀 Iniciando despliegue automático: rama [$BRANCH]"
echo "=================================================="

cd "$PROJECT_DIR"

# 1. Obtener los últimos cambios de Git
echo "📥 Descargando cambios desde GitHub..."
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

# 2. Sincronizar código fuente al contenedor en ejecución
echo "📦 Sincronizando código fuente al contenedor..."
docker cp "$PROJECT_DIR/app/src/." "$CONTAINER_NAME:/var/www/html/src/"
docker cp "$PROJECT_DIR/app/templates/." "$CONTAINER_NAME:/var/www/html/templates/"
docker cp "$PROJECT_DIR/app/assets/." "$CONTAINER_NAME:/var/www/html/assets/"
docker cp "$PROJECT_DIR/app/migrations/." "$CONTAINER_NAME:/var/www/html/migrations/"
docker cp "$PROJECT_DIR/app/config/." "$CONTAINER_NAME:/var/www/html/config/"
docker cp "$PROJECT_DIR/app/public/." "$CONTAINER_NAME:/var/www/html/public/"

# 3. Recompilar assets frontend si hay cambios en assets/
if [ "$2" == "--build" ] || [ "$2" == "--full" ]; then
    echo "🔨 Reconstruyendo contenedor completo (assets y dependencias)..."
    docker compose build app
    docker compose up -d --no-deps app
fi

# 4. Ejecutar migraciones pendientes de base de datos
echo "🔄 Ejecutando migraciones de base de datos..."
docker exec "$CONTAINER_NAME" php bin/console doctrine:migrations:migrate --no-interaction || true

# 5. Limpiar caché de Symfony en producción
echo "🧹 Limpiando caché de producción..."
docker exec "$CONTAINER_NAME" php bin/console cache:clear

echo "=================================================="
echo "✅ Despliegue completado con éxito en: $(date)"
echo "=================================================="

