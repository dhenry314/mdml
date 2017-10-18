# MDML Data Mill Aggregation System: API

This API provides a means of communication for the MDML, providing separate endpoints for ingest, mapping, and publishing.

## Installation

1. Ensure PHP is installed by executing `php -v` at a command line
    * [Installation and Configuration](http://php.net/manual/en/install.php) provides instructions on installing PHP
2. Ensure MySQL is installed
    * MySQL can be installed via a package manager or via the [installer](https://dev.mysql.com/downloads/installer/) downloaded from the official site
3. Create a MySQL table called `mhsmdml`
    * This can be done at the command line by entering `mysql -u root -p` then `CREATE DATABASE mhsmdml;`
4. There are two ways of storing records, either in a MongoDB database or directly on the file system.
    * If you choose to use MongoDB for storage, ensure MongoDB and the MongoDB PHP Driver are installed.
        * For OS specific instructions, see [Install MongoDB](https://docs.mongodb.com/manual/installation/)
        * [Installing/Configuring MongoDB](http://php.net/manual/en/mongodb.installation.php) provides instructions on for the MongoDB PHP Driver
5. Regardless of the storage system you choose to use, you **must** create a *cache* directory. 
    * Execute the following at a command line to create and change the permissions of the *chache* directory: `mkdir cache; chmod 744 cache;`
6. Make a copy of the example config file with the command `cp config.php.example to config.php`. Open *config.php* and update the fields to reflect the current installation.
        - The following fields **must** by updated: `db['connectStr']`, `db['user']`, `db['pw']`, and `'cacheDir'`
7. Run `composer install` to load dependencies
8. Execute `install.php` to setup the rest of the environment
9. Before accessing the API, set the Apache web directory to allow an .htaccess file. The setup should look something like this:
```
<Directory /var/www/mdmlMHS/>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
</Directory>
```
10. If Apache is using virtual hosts, create a symbolic link from the '/public' directory to a web accessible directory. Update the `'BASE_PATH'` field in the *config.php* to the web accessible directory.
11. **[OPTIONAL]** If you would like to add new users, make a copy of the user config file with the command `cp Users.example.php users.php`. Insert new users into the new *users.php*

## Endpoints

### **Create Record**
----

Posts json data to an endpoint.

#### **URL**

`{endpointPath}`

#### **Method:**

`POST`
  
#### **Data Params**

```json
{
    "@context": {
        "mdml": "http:\/\/data.mohistory.org\/mdml#"
    },
    "mdml:originURI": "http:\/\/data.mohistory.org\/example\/sourceA\/123459",
    "mdml:sourceURI": "http:\/\/data.mohistory.org\/example\/sourceA\/123459",
    "mdml:payloadSchema": "http://data.mohistory.org/files/testSchema.json",
    "mdml:payload": {
        "dateCreated": "2017-09-15",
        "content": "whatever"
    }
}
```

#### **Success Response:**

##### Code: 

`200`

##### Content: 
    
```json
{
    "@context": {
        "mdml": "http:\/\/data.mohistory.org\/mdml#"
    },
    "mdml:originURI": "http:\/\/data.mohistory.org\/example\/sourceA\/123459",
    "mdml:sourceURI": "http:\/\/data.mohistory.org\/example\/sourceA\/123459",
    "mdml:payloadSchema": "http://data.mohistory.org/files/testSchema.json",
    "mdml:payload": {
        "dateCreated": "2017-09-15",
        "content": "whatever"
    }
}
```
 
#### **Error Response:**

##### Code:

`404 NOT FOUND`

##### Content: 

`none`

**OR**

##### Code: 

`401 UNAUTHORIZED`

##### Content: 

```json
{
    "ErrorMessage": "ERROR: Invalid token!"
}
```
    
**OR** 
  
##### Code: 

`500 INTERNAL SERVER ERROR`

##### Content:

```json
{
    "exception": "RESTServiceException",
    "message": "Could not validate payload: Could not validate json with schemaPath: http://data.mohistory.org/files/testSchema.json ERRORS: The object must contain the properties [\"content\"]."
}
```

#### Sample Call:

```javascript
$.ajax({
    url: "http://data.mohistory.org/mdmlEndpointsV2/sandbox/",
    dataType: "json",
    data: {
        "@context": {
            "mdml": "http:\/\/data.mohistory.org\/mdml#"
        },
        "mdml:originURI": "http:\/\/data.mohistory.org\/example\/sourceA\/123459",
        "mdml:sourceURI": "http:\/\/data.mohistory.org\/example\/sourceA\/123459",
        "mdml:payloadSchema": "http://data.mohistory.org/files/testSchema.json",
        "mdml:payload": {
            "dateCreated": "2017-09-15",
            "content": "whatever"
        }
    } 
    type : "POST",
    headers: {
        "Authorization" :"Bearer " + jwt,
        "Content-Type" :"application/json"
    },
    success : function(r) {
        console.log(r);
    }
});
```

<!--@TODO Use https://gist.github.com/iros/3426278 as a template--!>
