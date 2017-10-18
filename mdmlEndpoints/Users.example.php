<?php
$Users = array(
  'mdmlAdmin'=>array(
		'password'=>'some_password',
		'email'=>'admin@example.org',
                'paths'=>array(
                        '/myExample/serviceCall/ingest*'=>'rwx',
			'/myEndpoints/fooIngest*'=>'r',
			'/anotherEndpoint/barMapped*'=>'rw'
                )
        )
);
?>
