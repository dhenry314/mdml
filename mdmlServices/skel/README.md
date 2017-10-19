## mdmlService Skeleton

#INSTALLATION

1. Copy config.php.example to config.php and set the values according to your installation

2. Create a sym link from /public to a web accessible directory.  Add that web accessible directory as 'BASE_PATH' to config.php

3. Run 'composer install'

4. Set the Apache web directory to allow an .htaccess file
```
<Directory /var/www/webAccessibleDirectory/>
       Options Indexes FollowSymLinks MultiViews
       AllowOverride All
</Directory>
```

5. Run 'composer test' to check your installation




