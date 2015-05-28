<?php
/**
 * Google Analytics PHP Wrapper for Google API Library
 *
 *
 * @copyright Mashhood Rastgar <mashhoodr@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Mashhood Rastgar <mashhoodr@gmail.com>
 * @version 0.1
 *
 */

require_once('google-api-php-client/src/Google/autoload.php');

class GoogleAnalyticsWrapper {

  private $client = null;
  private $analytics = null;


  /**
   * Constructor function for all new gapi instances
   *
   * Set up authenticate with Google and get auth_token
   *
   * @param String $applicationName => Can be any name
   * @param String $ServiceAccountEmail => From Google Developer console (email field)
   * @param String $keyFileLocation => The path to
   * @return GoogleAnalyticsWrapper
   */
  public function __construct($applicationName, $serviceAccountEmail, $keyFileLocation) {
    $client = new Google_Client();
    $client->setApplicationName($applicationName);
    $analytics = new Google_Service_Analytics($client);

    // Read the generated client_secrets.p12 key.
    $key = file_get_contents($keyFileLocation);
    $cred = new Google_Auth_AssertionCredentials(
        $serviceAccountEmail,
        array(Google_Service_Analytics::ANALYTICS_READONLY),
        $key
    );

    $client->setAssertionCredentials($cred);
    if($client->getAuth()->isAccessTokenExpired()) {
      $client->getAuth()->refreshTokenWithAssertion($cred);
    }

    $this -> client = $client;
    $this -> analytics = $analytics;
  }

  /**
   * Process filter string, clean parameters and convert to Google Analytics
   * compatible format
   *
   * @param String $filter
   * @return String Compatible filter string
   */
  private function processFilter($filter) {
    if(!isset($filter)) {
      return false;
    }

    $valid_operators = '(!~|=~|==|!=|>|<|>=|<=|=@|!@)';

    $filter = preg_replace('/\s\s+/',' ',trim($filter)); //Clean duplicate whitespace
    $filter = str_replace(array(',',';'),array('\,','\;'),$filter); //Escape Google Analytics reserved characters
    $filter = preg_replace('/(&&\s*|\|\|\s*|^)([a-z]+)(\s*' . $valid_operators . ')/i','$1ga:$2$3',$filter); //Prefix ga: to metrics and dimensions
    $filter = preg_replace('/[\'\"]/i','',$filter); //Clear invalid quote characters
    $filter = preg_replace(array('/\s*&&\s*/','/\s*\|\|\s*/','/\s*' . $valid_operators . '\s*/'),array(';',',','$1'),$filter); //Clean up operators

    if(strlen($filter) > 0) {
      return $filter;
    } else {
      return false;
    }
  }

  /**
   * Parses the data so the rows include the column names
   *
   */
  private function parseData($result) {
    $columns = $result -> columnHeaders;
    $data = $result -> rows;
    $parsedResult = array();

    if($data && $columns) {

        foreach($data as $item) {
            $parsedItem = array();
            foreach($columns as $index => $column) {
                $parsedItem[$column -> name] = $item[$index];
            }
            $parsedResult[] = $parsedItem;
        }
    }

    return $parsedResult;
  }


  /**
   * Runs the query using the Google API PHP Library
   *
   * @param String $profileId => the profile id of your Google Analytics Account
   * @param String $metrics => metrics to pass to the query. Can be an array or comma seperated list
   * @param String $startDate => Get data from YYYY-MM-DD, ndaysAgo, today or yesterday
   * @param String $endDate => Get data to YYYY-MM-DD, ndaysAgo, today or yesterday
   * @param Array options => The additional options array
   * @return Array of results (rows) with their columns as keys (same as metrics / dimensions passed)
   */
  public function query($profileId, $metrics = 'ga:sessions', $startDate = '30daysAgo', $endDate = 'today', $options = array()) {

    if(!isset($profileId) || !is_string($profileId)) {
      throw new Exception('Profile ID needs to be set and should be a string e.g. ga:86055307');
    }

    if(isset($options['filters'])) {
      // TODO vaildate filters and throw exception
      $options['filters'] = $this -> processFilter($options['filters']);
    }

    if(isset($options['dimensions']) && is_array($options['dimensions'])) {
      $options['dimensions'] = implode(',', $options['dimensions']);
    }

    if(is_array($metrics)) {
      $metrics = implode(',', $metrics);
    }

    $result = $this -> analytics -> data_ga -> get($profileId, $startDate, $endDate, $metrics, $options);

    if($result) {
        return $this -> parseData($result);
    }

    throw new Exception('Result was null, something failed when getting results from GA.');
  }
}
