<?php
/**
 * ShabbosTimes plugin for phplist
 * 
 *
 * This plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * @category  phplist
 * @package   ShabbosTimes
 * @author    Moshe Kaplan
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */
 
class shabbostimes extends phplistPlugin
{
  public $name = "ShabbosTimes plugin for phpList";
  public $coderoot = "shabbostimes/";
  public $version = "0.3";
  public $description = 'Replaces [CANDLELIGHTING] and [PARSHA] with the candlelighting and parsha';
  public $settings = array(
    "shabbostimes_zipcode" => array (
      'value' => "",
      'description' => "Zipcode to use for zmanim",
      'type' => "text",
      'allowempty' => 0,
      "max" => 1000,
      "min" => 0,
      'category'=> 'ShabbosTimes',
    ),
  );
  
    function shabbostimes(){
        parent::phplistplugin();
        $this->coderoot = dirname(__FILE__).'/shabbostimes/';
    }
    
    function activate(){
        return true;
    }
    
    function get_hebcal_data($zipcode){
        // Retrieves the data from hebcal
        // http://www.hebcal.com/home/197/shabbat-times-rest-api
        $hebcal_url = 'http://www.hebcal.com/shabbat/?cfg=json&geo=zip&zip='.$zipcode.'&m=0&a=on';

        // http://stackoverflow.com/questions/16700960/how-to-use-curl-to-get-json-data-and-decode-the-data
        // Will dump a beauty json :3
        //  Initiate curl
        $ch = curl_init();
        // Disable SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // Will return the response, if false it print the response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Set the url
        curl_setopt($ch, CURLOPT_URL,$hebcal_url);
        // Execute
        $result=curl_exec($ch);
        // Closing
        curl_close($ch);

        return json_decode($result, true);
    }

    function parse_hebcaldata($hebcal_data){
        $parsha = NULL;
        $candlelighting = NULL;
        $saturdaydate = NULL;
        
        foreach ($hebcal_data["items"] as $item) {
            if ($item['category'] == 'parashat'){
                // Get this weeks Parsha and the date
                $parsha_string = $item["title"];
                $parsha_exploded = explode('Parshas ', $parsha_string, 2);
                $parsha = $parsha_exploded[1];

                $saturdaydate = $item["date"];
            }
            else if ($item['category'] == 'candles'){
                $candlelighting_string = $item["title"];
                $candlelighting_exploded = explode(': ', $candlelighting_string, 2);
                $candlelighting = $candlelighting_exploded[1];
            }
        }
        return array ($parsha, $candlelighting, $saturdaydate);
    }


    function replace($content){
        $zipcode = getConfig('shabbostimes_zipcode');
        if(!$zipcode){
          // Error, but we can't do anything.
          return $content;
        }

        // If needed, create the table here
        $shabbos_tablename = "shabbostimes";
        $shabbos_tablestructure = array(
                                "zipcode" => array("varchar(5) not null","zipcode"),
                                "saturdaydate" => array("DATE","Date of Saturday"),
                                "candlelighting" => array("varchar(80) not null","Time of Candlelighting"),
                                "parsha" => array("varchar(80) not null","Parsha")
                            );
        // Create the table if it doesn't exist
        $req = Sql_Query(sprintf('select table_name from information_schema.tables where table_schema = "'.$GLOBALS['database_name'].'" AND table_name="%s"', $shabbos_tablename));
        if (! Sql_Fetch_Row($req)) {
            Sql_create_Table ($shabbos_tablename,$shabbos_tablestructure);
        }

        $parsha = NULL;
        $candlelighting = NULL;

        // First see if we already have the info in the DB.
        $last_saturday = date('Y-m-d', strtotime('last saturday'));
        $next_saturday = date('Y-m-d', strtotime('saturday'));

        $query_result = Sql_Query(sprintf('SELECT parsha, candlelighting FROM %s
                            WHERE (zipcode="%s" AND DATE(saturdaydate) BETWEEN DATE("%s") AND DATE("%s") )',
                            $shabbos_tablename,
                            $zipcode,
                            $last_saturday,
                            $next_saturday));
        if ($result = Sql_Fetch_Array($query_result)){
            // Found in DB
            $parsha = $result[0];
            $candlelighting = $result[1];
        }
        else{
            // Not in the DB. Need to get it from hebcal
            $hebcal_data = $this->get_hebcal_data($zipcode);
            list ($parsha, $candlelighting, $saturdaydate) = $this->parse_hebcaldata($hebcal_data);
            // Store it for next time
            Sql_Query(sprintf('INSERT INTO %s (zipcode, saturdaydate, parsha, candlelighting)
                                VALUES
                                ("%s", "%s", "%s", "%s")',
                            $shabbos_tablename,
                            $zipcode,
                            $saturdaydate,
                            $parsha,
                            $candlelighting));
        }

        // Now that $parsha and $candlelighting are set:
        $content = str_replace("[CANDLELIGHTING]", $candlelighting, $content);
        $content = str_replace("[PARSHA]", $parsha, $content);
        return $content;
    }
    
      /* 
   * parseOutgoingTextMessage
   * @param integer messageid: ID of the message
   * @param string  content: entire text content of a message going out
   * @param string  destination: destination email
   * @param array   userdata: associative array with data about user
   * @return string parsed content
   */
  function parseOutgoingTextMessage($messageid, $content, $destination, $userdata = null) {
    return $this->replace($content);
  }

  /* 
   * parseOutgoingHTMLMessage
   * @param integer messageid: ID of the message
   * @param string  content: entire text content of a message going out
   * @param string  destination: destination email
   * @param array   userdata: associative array with data about user
   * @return string parsed content
   */
  function parseOutgoingHTMLMessage($messageid, $content, $destination, $userdata = null) {
    return $this->replace($content);
  }
}
?>
