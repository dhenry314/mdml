#!/usr/bin/php
<?php
/**
 * Export to PHP Array plugin for PHPMyAdmin
 * @version 4.7.1
 */

/**
 * Database `MetadataMill`
 */

/* `MetadataMill`.`remote_sets` */
$remote_sets = array(
  array('ID' => '439','set_spec' => 'mdh_all','remote_set' => 'msaceg','alias' => NULL),
  array('ID' => '440','set_spec' => 'mdh_all','remote_set' => 'jessejames','alias' => NULL),
  array('ID' => '441','set_spec' => 'mdh_all','remote_set' => 'moconserv','alias' => NULL),
  array('ID' => '442','set_spec' => 'mdh_all','remote_set' => 'divtour','alias' => NULL),
  array('ID' => '443','set_spec' => 'mdh_all','remote_set' => 'mocases','alias' => NULL),
  array('ID' => '444','set_spec' => 'mdh_all','remote_set' => 'p16795coll1','alias' => NULL),
  array('ID' => '445','set_spec' => 'mdh_all','remote_set' => 'msaboggs','alias' => NULL),
  array('ID' => '446','set_spec' => 'mdh_all','remote_set' => 'msareynold','alias' => NULL),
  array('ID' => '447','set_spec' => 'mdh_all','remote_set' => 'msaedwards','alias' => NULL),
  array('ID' => '448','set_spec' => 'mdh_all','remote_set' => 'msaking','alias' => NULL),
  array('ID' => '449','set_spec' => 'mdh_all','remote_set' => 'msapolk','alias' => NULL),
  array('ID' => '450','set_spec' => 'mdh_all','remote_set' => 'msamerge','alias' => NULL),
  array('ID' => '451','set_spec' => 'mdh_all','remote_set' => 'msaphelps','alias' => NULL),
  array('ID' => '452','set_spec' => 'mdh_all','remote_set' => 'msamarm','alias' => NULL),
  array('ID' => '453','set_spec' => 'mdh_all','remote_set' => 'msafran','alias' => NULL),
  array('ID' => '454','set_spec' => 'mdh_all','remote_set' => 'msastone','alias' => NULL),
  array('ID' => '455','set_spec' => 'mdh_all','remote_set' => 'p16795coll6','alias' => NULL),
  array('ID' => '456','set_spec' => 'mdh_all','remote_set' => 'msa','alias' => NULL),
  array('ID' => '457','set_spec' => 'mdh_all','remote_set' => 'msaphotos','alias' => NULL),
  array('ID' => '458','set_spec' => 'mdh_all','remote_set' => 'housej','alias' => NULL),
  array('ID' => '459','set_spec' => 'mdh_all','remote_set' => 'senatej','alias' => NULL),
  array('ID' => '460','set_spec' => 'mdh_all','remote_set' => 'bluebook','alias' => NULL),
  array('ID' => '461','set_spec' => 'mdh_all','remote_set' => 'regdarpen','alias' => NULL),
  array('ID' => '462','set_spec' => 'mdh_all','remote_set' => 'rgdarpenind','alias' => NULL),
  array('ID' => '463','set_spec' => 'mdh_all','remote_set' => 'p16795coll5','alias' => NULL),
  array('ID' => '464','set_spec' => 'mdh_all','remote_set' => 'postjc','alias' => NULL),
  array('ID' => '465','set_spec' => 'mdh_all','remote_set' => 'vetstj','alias' => NULL)
);

$oai_sets = array();

foreach($remote_sets as $data) {
	$oai_sets[] = $data['remote_set'];
}

$result = json_encode($oai_sets);

die($result);

?>

