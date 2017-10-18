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

5. Ensure that there is a symlink from 'mdml' to $MDMLCore/mdmlServices/lib/

6. Run 'composer test' to check your installation

7. Get a JWT token from your login service and put the token in the url params as 'jwt'.  
	E.G. http://example.org/myServices/TestService?jwt=e9fh5JK67df59JH...

8. Calling TestService as shown above should display "Hello World!"



