<?php

class Scrapper
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
    private array $secondPages = [];
    /**
     * @var array
     */
    private array $imgWideLinks = [];
    /**
     * @var array
     */
    private array $imgPortretLinks = [];

    public function __construct()
    {
    }

    public function parseMain(string $nextPage = "")
    {
        echo "Waiting $this->sleep seconds before next request\n";
        sleep($this->sleep);

        $nextPage = $nextPage != "" ? $nextPage : $this->url;
        echo "$this->pageNo-------------------------------\n";
        echo "Getting content from main page\n";
        echo "$nextPage \n";

        $mainContent = $this->getContent($nextPage);

        preg_match_all('/<h2>\s*<a\s*href=.(.+)["\']/isU', $mainContent, $matches);
        if (isset($matches[1])) {
            echo "Found " . count($matches[1]) . " links:\n";
            print_r($matches[1]);
            $this->secondPages = array_merge($matches[1], $this->secondPages);
        }

        preg_match('/next\s*page-numbers\s*["\']\s*href=.(.+)["\']/isU', $mainContent, $match);
        if (isset($match[1])) {
            echo "Next page found:\n";
            $this->pageNo++;
            $this->parseMain($match[1]);
        } else {
            $file = 'links_second_pages.js';
            echo "Finished parsing the main pages\n";
            echo "Saving links in file $file \n";

            file_put_contents($file, json_encode($this->secondPages));
        }
    }

    public function parseSecond()
    {
        $wideImage = 'wide_image_links.js';
        $portretImage = 'protret_image_links.js';
        $links = json_decode(file_get_contents('links_second_pages.js'));

        $total_links = count($links);
        echo "Parsing " . $total_links . " links\n";

        $cnt = 1;
        foreach ($links as $link) {
            echo "$cnt / $total_links -------------------------------\n";
            echo "Getting content from link page\n";
            echo "$link \n";
            $content = $this->getContent($link);

            preg_match('/image["\'],\s*["\']url["\']\s*\:\s*["\'](.+\.jpg)["\']\s*,["\']wid/isU', $content, $match);
            if (isset($match[1])) {
                echo "Found wide image " . $match[1] . "\n";
                $this->imgWideLinks[] = $match[1];
                file_put_contents($wideImage, json_encode($this->imgWideLinks));
            }

            preg_match('/<br.+<a\s*href=.(.+)["\']\s*>\s*<img\s*loading=/isU', $content, $match);
            if (isset($match[1])) {
                echo "Found portret image " . $match[1] . "\n";
                $this->imgPortretLinks[] = $match[1];
                file_put_contents($portretImage, json_encode($this->imgPortretLinks));
            }

            $cnt++;

            echo "Waiting $this->sleep seconds before next request\n\n";
            sleep($this->sleep);
        }

        echo "Finished parsing images links\n";

        echo "Saving image links in files $portretImage and $wideImage \n";

        file_put_contents($portretImage, json_encode($this->imgPortretLinks));
        file_put_contents($wideImage, json_encode($this->imgWideLinks));

        echo "Done\n";
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
            file_put_contents('images/wide/' .end($imgArray), file_get_contents($imgUrl));
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

// (new Scrapper())->parseMain();
// (new Scrapper())->parseSecond();
// (new Scrapper())->downloadWideImages();
// (new Scrapper())->downloadPortretImages();
