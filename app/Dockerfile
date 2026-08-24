# Economic Card - SaaS de cartão econômico (PHP + MySQL)
FROM php:8.2-apache

# Extensões PHP exigidas pela aplicação
RUN docker-php-ext-install mysqli \
    && a2enmod rewrite

# Permissões
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .

EXPOSE 80

# Configuração via variáveis de ambiente:
#   DB_HOST, DB_USER, DB_PASS, DB_NAME            -> conexão MySQL (config.php)
#   GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET        -> login com Google
#   FB_APP_ID, FB_APP_SECRET                      -> login com Facebook
#   TURNSTILE_SITE_KEY, TURNSTILE_SECRET          -> Cloudflare Turnstile
#   ADMIN_API_SENHA                               -> senha da área "API Pagamento" do admin
#
# Exemplo de uso com banco separado:
#   docker run -d -p 8080:80 \
#     -e DB_HOST=host.docker.internal \
#     -e DB_USER=root -e DB_PASS=senha -e DB_NAME=economicacard \
#     -e TURNSTILE_SITE_KEY=... -e TURNSTILE_SECRET=... \
#     economiccard
