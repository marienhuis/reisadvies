<?php
/**
 * Collection of utils
 *
 * @package    Reisadvies
 * @author     Marien Huis
 */

function saveJsonToFile($data, $filename){
	$data  = json_encode($data);
	$fp = fopen($filename, 'w');
	fwrite($fp, $data);
	fclose($fp);
	return $data;
}

function readJsonFile($file){
	if(is_file($file)){
		return json_decode(file_get_contents($file));
	}else{
		return false;
	}
	
}