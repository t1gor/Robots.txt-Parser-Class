Robots.txt php parser class
=====================

Php class to parse robots.txt rules according to Google & Yandex specifications.

Example:
````
$robotsTxtFileContent = file_get_content('http://google.com/robots.txt');
$robotsTxtFileEncoding = "UTF-8"; // recommended.
$parser = new robotstxtparser($robotsTxtFileContent, $robotsTxtFileEncoding);
$ruleValid = $parser->checkRule(robotstxtparser::ROBOTS_TXT_DISALLOW, '/linux/ololo'); 
````
