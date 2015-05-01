<?php
/**
 * Console
 *
 * @category   Reisadvies
 * @package    Reisadvies
 * @subpackage Console
 * @copyright  Marien Huis
 */


include "utils.php";
include "resizeMaps.php";

class Console{


	private $allowedCommands = array(
		"resizeModifiedMaps",
		"resizeAllMaps",
		"lastUsed",
		"mapCount",
		"clearMaps",
		"updateCountry",
		"refreshCountriesCache"
		);

	private $resizeMaps;
	
	//First function that is always called
	public function run($argv){
		$this->resizeMaps = new ResizeMaps();

		if(count($argv) > 1){
			//Only execute allowed functions
			if(in_array($argv[1], $this->allowedCommands)){
				$this->{$argv[1]}();
			}else{
				echo "Command not found".PHP_EOL;
			}
		}else{
			echo "The following commands are allowed:".PHP_EOL;
			print_r($this->allowedCommands);
		}
	}
	//Get all the modified countries since 2012 and download all the maps
	// call this function after clearMaps() to re-populate the maps folder
	public function resizeAllMaps(){
		$this->resizeMaps->setModifiedDate('20120101');
		$this->resizeMaps->downloadAndResizeMaps();
	}

	//Get all modified countries since yesterday
	// if modified countries are allready processed do nothing
	public function resizeModifiedMaps(){
		$this->resizeMaps->downloadAndResizeMaps();
	}

	//Returns the number of downloaded maps
	public function mapCount(){
		$path = $this->resizeMaps->getSavedImagePath();
		$fi = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
		printf("There are %d maps in cache".PHP_EOL, iterator_count($fi));
	}

	//Remove all the downloaded maps
	public function clearMaps(){
		$path = $this->resizeMaps->getSavedImagePath();
		unlink('last_processed_date.json');
		$fi = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
		while($fi->valid()){
			unlink($fi->getPathname());
			echo "Deleted: ".$fi->getFileName().PHP_EOL;
			$fi->next();
		}
	}

	public function updateCountry(){
		echo "This function is not yet implemented, please use the resizeAllMaps()";
	}


	public function refreshCountriesCache(){
		$countries = json_decode($this->resizeMaps->getCountries());
		echo count((array)$countries)." countries inserted into to cache".PHP_EOL;
	}

}

$console = new Console();
$console->run($argv);