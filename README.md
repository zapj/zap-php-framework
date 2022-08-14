# zap-php-framework


### Rewrite

#### Apache
````apacheconf
Options +FollowSymLinks -Indexes
RewriteEngine On
 
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
 
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
````


#### Nginx
````apacheconf
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
````