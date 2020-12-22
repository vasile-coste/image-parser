<?php
require 'db/Connect.php';

class ImageParser
{
    /**
     * @var int
     */
    private int $pageNo = 1;

    /**
     * @var int
     */
    private int $sleep = 3;

    /**
     * @var string
     */
    private string $url = "https://windows10spotlight.com/";

    /**
     * @var array
     */
    private array $imgWideLinks = [];

    /**
     * @var array
     */
    private array $imgPortretLinks = [];

    /**
     * Making things simpliers by addin them in the construct function 
     */
    public function __construct()
    {
        $this->db = Connect::PDO();
        $this->run();
    }

    /**
     * Build the logic call
     */
    private function run()
    {
        echo "Parsing Main pages -------------- \n\n";
        $this->parseMain($this->url);
        echo "Getting images links -------------- \n\n";
        $this->parseSecond();
    }

    /**
     * Parse main page to retrieve the links that will be later used to retrieve the images URLs
     * 
     * @param string $nextPage
     */
    public function parseMain(string $nextPage)
    {
        if ($nextPage != $this->url) {
            echo "Waiting $this->sleep seconds before next request\n\n";
            sleep($this->sleep);
        }

        echo "$this->pageNo-------------------------------\n";
        echo "Getting content from main page\n";
        echo "$nextPage \n";

        $mainContent = $this->getContent($nextPage);
        $continue = true;

        preg_match_all('/<h2>\s*<a\s*href=.(.+)["\']/isU', $mainContent, $matches);
        if (isset($matches[1])) {
            echo "Found " . count($matches[1]) . " links:\n";
            print_r($matches[1]);

            foreach ($matches[1] as $link) {
                echo "Checking link $link \n";

                $stmt = $this->db->prepare("SELECT `id` FROM `pages` WHERE `link`=:link");
                $stmt->execute([
                    'link' => $link
                ]);
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($records) == 0) {
                    $sql = "INSERT INTO `pages` (`link`, `parsed`) VALUES (:link, :parsed)";
                    echo "$sql\n\n";

                    $stmt = $this->db->prepare($sql);
                    if (!$stmt) {
                        echo "\nPDO::errorInfo():\n";
                        print_r($this->db->errorInfo());
                        exit();
                    }
                    $stmt->execute([
                        'link' => $link,
                        'parsed' => 0
                    ]);
                } else {
                    echo "Link exists... will skip\n";
                    $continue = false;
                }
            }
        }

        preg_match('/next\s*page-numbers\s*["\']\s*href=.(.+)["\']/isU', $mainContent, $match);
        if (isset($match[1]) && $continue == true) {
            echo "Next page found:\n";
            $this->pageNo++;
            $this->parseMain($match[1]);
        } else {
            echo "Finished parsing the main pages\n";
            echo "Total parsed pages: $this->pageNo\n";
        }
    }

    /**
     * Get the image urls from the URls parted from parseMain()
     */
    public function parseSecond()
    {
        $records = $this->db
            ->query("SELECT `id`, `link` FROM `pages` WHERE `parsed`=0 OR `regex_error_landscape`=1 OR `regex_error_portret`=1")
            ->fetchAll(PDO::FETCH_ASSOC);

        $total_links = count($records);
        echo "Parsing " . $total_links . " links\n";

        $cnt = 1;
        foreach ($records as $data) {
            $link = $data['link'];
            echo "$cnt / $total_links -------------------------------\n";
            echo "Getting content from link page\n";
            echo "$link \n";

            // getting content from url
            $content = $this->getContent($link);

            // checkers in case we fail to get the images using regex
            $regexErrorLandscape = false;
            $regexErrorPortret = false;

            //get landscape images
            preg_match('/<figure[^>]*>\s*<a\s*href=["\'](.+\.jpg)["\']/isU', $content, $match);
            if (isset($match[1])) {
                echo "Found wide image " . $match[1] . "\n";
                $this->addImageUrlToDB($match[1], "landscape");
            } else {
                echo "Waring: Landscape image not found. Please check regex \n";
                $regexErrorLandscape = true;
            }

            // get portret images
            preg_match('/<(br|p)[^>]*>\s*<a\s*href=.([^\'"]+)["\'][^>]*>\s*<img\s*loading/isU', $content, $match);
            if (isset($match[2])) {
                echo "Found portret image " . $match[2] . "\n";
                $this->addImageUrlToDB($match[2], "portret");
            } else {
                echo "Waring: Portret image not found. Please check regex \n";
                $regexErrorPortret = true;
            }

            // set page as parsed
            $updateArray = [
                "`parsed`=1"
            ]; 
            $updateArray[] = "`regex_error_landscape`=". ($regexErrorLandscape ? 1 : 0);
            $updateArray[] = "`regex_error_portret`=". ($regexErrorPortret ? 1 : 0);

            $updateSql = "UPDATE `pages` SET ".implode(", ", $updateArray)." WHERE id=" . $data['id'];
            echo "$updateSql \n\n";
            $this->db->query($updateSql);

            echo "Waiting $this->sleep seconds before next request\n\n";
            sleep($this->sleep);
            $cnt++;
        }

        echo "Finished parsing images links\n";
        echo "Done\n";
    }

    /**
     * Save image url to DB for later download
     * 
     * @param string $link
     * @param string $type
     */
    private function addImageUrlToDB(string $link, string $type)
    {
        $stmt = $this->db->prepare("SELECT `id` FROM `images` WHERE `link`=:link");
        $stmt->execute([
            'link' => $link
        ]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($records) == 0) {
            $sql = "INSERT INTO `images` (`link`, `type`, `parsed`) VALUES (:link, :type, :parsed)";
            echo "$sql\n\n";

            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                echo "\nPDO::errorInfo():\n";
                print_r($this->db->errorInfo());
                exit();
            }

            $stmt->execute([
                'link' => $link,
                'type' => $type,
                'parsed' => 0
            ]);
        } else {
            echo "Link exists... will skip\n";
        }
    }

    public function downloadWideImages()
    {
        $wides = json_decode(file_get_contents('wide_image_links.js'));
        $totalWides = count($wides);

        echo "Downloading " . $totalWides . " images\n";
        $cnt = 1;
        foreach ($wides as $imgUrl) {
            echo "$cnt / $totalWides -------------------------------\n";
            echo "Saving image $imgUrl\n";
            $imgArray = explode("/", $imgUrl);
            file_put_contents('images/wide/' . end($imgArray), file_get_contents($imgUrl));
            $cnt++;
        }
        echo "\nDone\n";
    }

    public function downloadPortretImages()
    {
        $portrets = json_decode(file_get_contents('protret_image_links.js'));
        $totalPortrets = count($portrets);

        echo "Downloading " . $totalPortrets . " images\n";
        $cnt = 1;
        foreach ($portrets as $imgUrl) {
            echo "$cnt / $totalPortrets -------------------------------\n";
            echo "Saving image $imgUrl\n";
            $imgArray = explode("/", $imgUrl);
            file_put_contents('images/portret/' . end($imgArray), file_get_contents($imgUrl));
            $cnt++;
        }
        echo "\nDone\n";
    }

    /**
     * Get page content using cURL
     * 
     * @param string $url
     * 
     * @return string
     */
    private function getContent(string $url): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }
}

new ImageParser();
// (new Scrapper())->parseSecond();
// (new Scrapper())->downloadWideImages();
// (new Scrapper())->downloadPortretImages();
