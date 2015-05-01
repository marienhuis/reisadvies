<?php
/**
 * This class contains all the functions needed to download and resize images
 * 	that are a part of a reisadvies. It can dowload all the maps that have changes
 *	since a particular data (default is last 2 days).
 *
 * @package    Reisadvies
 * @author     Marien Huis
 */

class ResizeMaps{

	private $pngQuality = 6;
	private $jpegQuality = 80;
	private $savedImagePath = "maps/";
	private $openDataUrl = "http://opendata.rijksoverheid.nl/v1/sources/rijksoverheid/infotypes/traveladvice/";
	private $counriesUrl = "http://www.minbuza.nl/restservice/countries/";
	private $openDataRows = 200;
	private $modifiedDate;

	/**
	  * Constructor that sets the modified date to 2 days ago
	  *
	  * @return void
	  */
	function __construct() {
		$this->modifiedDate = date("Ymd",mktime(0, 0, 0, date("m")  , date("d")-2, date("Y")));
	}

	/**
	  * Getter for the savedImagePath
	  *
	  * @return string
	  *
	  */
	public function getSavedImagePath(){
		return $this->savedImagePath;
	}
	
	/**
	  * Setter for the modifiedDate
	  *
	  * @param string $modifiedDate
	  *
	  * @return string
	  *
	  */
	public function setModifiedDate($modifiedDate){
		$this->modifiedDate = $modifiedDate;
	}

	/**
	  * Getter for the getModifiedDate
	  *
	  * @return string
	  *
	  */
	public function getModifiedDate(){
		return $this->modifiedDate;
	}

	/**
	  * Replaces / with a -
	  *
	  * TODO: Must be moved to utils
	  *
	  * @return string
	  *
	  */
	private function sanitizeString($input){
		$output = str_replace("/", "-", $input);
		//$output = utf8_decode($output);
		return $output;
	}

	/**
	  * Gets all the countries and save them in two json files for easy access
	  *
	  * These countries come from a different feed (BuZa). We need this info to match countries on ID
	  *
	  * @return array
	  *
	  */
	public function getCountries(){
		//Transform xml to json
		$countries = simplexml_load_file($this->counriesUrl);
		$countries = json_encode($countries);
		$countries = json_decode($countries);

		$countriesArrayKeyedByName = array();
		$countriesArrayKeyedById = array();
		//Put the countries in two arrays with different keys for easy lookup
		foreach ($countries->country as $key => $country) {
			$country->title = $this->sanitizeString($country->title);
			$countriesArrayKeyedByName[$country->title] = $country->countrycode;
			$countriesArrayKeyedById[$country->countrycode] = $country->title;
		}

		saveJsonToFile($countriesArrayKeyedById, "countries_by_id.json");
		return saveJsonToFile($countriesArrayKeyedByName, "countries_by_name.json");
	}

	/**
	  * Gets all the countries(reisadvies) that have been modified.
	  * The last processed date will be saved in a json file.
	  *
	  * @return array
	  *
	  */
	function getModifiedCountries($offset = 0){
		$countryUrls = array();

		//Get date of yesterday, this is needed for the url
		$dateToday = $this->modifiedDate;
		//$dateToday = "20120101";		
		
		$lastProcessedDate = readJsonFile('last_processed_date.json');
		if(is_object($lastProcessedDate)){
			$lastProcessedDate = $lastProcessedDate->lastProcessed;
		}else{
			$lastProcessedDate = null;
		}

		$url = $this->openDataUrl.'lastmodifiedsince/'.$dateToday.'/?output=json&rows='.$this->openDataRows.'&offset='.$offset;		

		//Download the JSON
		if($modifiedCountriesJson = json_decode(file_get_contents($url))){
			echo  "Found ".count($modifiedCountriesJson)." modified countries since ".$dateToday.PHP_EOL;
			foreach ($modifiedCountriesJson as $key => $modifiedCountry) {
				$countryUrls[] = $modifiedCountry->dataurl."?output=json";
				$newLastProcessedDate = $modifiedCountry->lastmodified;
			}
		}

		//Countries have allready been processed so we return a empty array
		if($newLastProcessedDate == $lastProcessedDate){
			echo "Countries allready processed".PHP_EOL;
			return array();
		}

		//Update the last_processed_date.json file
		saveJsonToFile(array('lastProcessed' => $newLastProcessedDate), 'last_processed_date.json');
		return $countryUrls;
	}

	/**
	  * Extract the image/map from the reisadvies
	  *
	  * @return array
	  *
	  */
	function getCountryMapsUrls($countryUrls){
		if(!is_array($countryUrls)){
			return array();
		}

		$mapUrls = array();
		//Loop trough the content and find the image
		foreach ($countryUrls as $key => $countryUrl) {
			$country = json_decode(file_get_contents($countryUrl));

			if(isset($country->content)){
				foreach ($country->content as $key => $content) {
					//check if there is no paragraphtitle and the paragraph only contains an image url
					if(!isset($content->paragraphtitle) && preg_match("/^http(.*)png/i", $content->paragraph)){
						$mapUrls[$this->sanitizeString($country->location)] = $content->paragraph;
						break;
					}
				}
			}
		}
		return $mapUrls;
	}

	/**
	  * Download all the maps from the modified countries
	  *
	  * @return void
	  *
	  */
	function downloadAndResizeMaps(){
		//There are 216 countries so we need a offset of 200 the second time
		$modifiedCountries = $this->getModifiedCountries(0); 

		if(count($modifiedCountries) == 200){
			$modifiedCountries = array_merge($modifiedCountries, $this->getModifiedCountries(200));
		}
		
		$countryMapsUrls = $this->getCountryMapsUrls($modifiedCountries);
		foreach ($countryMapsUrls as $key => $countryMapsUrl) {
			echo 'Download:'.$key.PHP_EOL;
			$this->saveImage($this->resize($countryMapsUrl), $key.".png");
		}
		
	}

	/**
	  * Remove the white bleed from the map
	  *
	  * @return resource
	  *
	  */
	function resize($url, $fileType = "png"){
		echo "Resize image: ".$url.PHP_EOL;
		//Download the image as an image resource
		$image = imagecreatefrompng($url);

		//Get the width and height
		$thumb_width = imagesx($image); 
		$thumb_height = imagesy($image); 

		//Bleeds
		//TODO: Remove hardcoded values
		$topBleed = 54;
		$bottomBleed = 48;
		$leftBleed = 30;
		$rightBleed = 30;

		//new size without the bleeds
		$new_width =  $thumb_width -($leftBleed+$rightBleed);
		$new_height = $thumb_height - ($topBleed+$bottomBleed);

		//Create canvas
		$thumb = imagecreatetruecolor($new_width ,$new_height);

		// Resize and crop
		imagecopyresampled(	$thumb,
							$image,
							-$leftBleed,
							-$topBleed,
							0,
							0,
							$thumb_width, //new
							$thumb_height, //new
							$thumb_width, 
							$thumb_height
							);

		return $thumb;
	}

	/**
	  * Save the image/map to disk
	  *
	  * @return void
	  *
	  */
	function saveImage($image, $imageName, $fileType = "png"){
		//$imageName = iconv("UTF-8","Windows-1251//TRANSLIT",$imageName);
		$imageName = strtolower($imageName);
		if($fileType == "png"){
			imagepng($image, $this->savedImagePath.$imageName, $this->pngQuality);
		}else{
			imagejpeg($image, $this->savedImagePath.$imageName, $this->jpegQuality);
		}
	}
}

//$resizeMaps = new ResizeMaps();


