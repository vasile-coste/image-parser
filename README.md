# Image Parser
## Usage:
- as it is now by `(new ImageParser())->run()`;
- can be called separetly like this:
- - `(new ImageParser())->parseMain()` will get all the pages containing images
- - `(new ImageParser())->parseSecond()` will get all the image links from the pages
- - `(new ImageParser())->downloadImages()` will download all the images using the links from previous step

## Notes:
- The script needs a MySql db
- For retrieving the web pages content i'm using cURL (i know i could use guzzle)