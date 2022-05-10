<?php

/**
 * Class StonetossExtension
 *
 * Highly inspired by https://framagit.org/dohseven/freshrss-explosm,
 * which itself is inspired by https://github.com/kevinpapst/freshrss-dilbert,
 *
 * @author Paul Tunbridge
 */
class StonetossExtension extends Minz_Extension
{
    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }

    public function handleConfigureAction()
    {
    }

    /**
     * Initialize this extension
     */
    public function init()
    {
        // Make sure to not run on server without libxml
        if (!extension_loaded('xml')) {
            return;
        }

        $this->registerHook('entry_before_insert', array($this, 'embedStonetoss'));
    }

    /**
     * Check if we support working on this entry.
     * We do not want to parse every displayed entry,
     * but only the Stonetoss ones ;-)
     *
     * @param FreshRSS_Entry $entry
     * @return bool
     */
    protected function supports($entry)
    {
        $link = $entry->link();

        if (stripos($link, 'stonetoss.com') === false) {
            return false;
        }

        return true;
    }


    /*
     * Replaces the thumbnail image in the entry
     * with the specified URL
     */
    protected function replaceThumbnailInEntry($entry,$info)
    {
        libxml_use_internal_errors(true);
        $article = new DOMDocument;
        $article->validateOnParse = true;
        $article->loadHTML($entry->content());
        libxml_use_internal_errors(false);

        $imageElements = $article->getElementsByTagName('img');

        if (!is_null($imageElements))
        {

            $image = $imageElements->item(0);

            $image->setAttribute('src', $info['src']);
            $image->setAttribute('alt', $info['alt']);
            $image->setAttribute('title', $info['title']);

            //Remove height and width so the image is the correct size
            $image->removeAttribute('height');
            $image->removeAttribute('width');

        }
        return $article;
    }

    /*
     * Fetches the URL and the title for the given entry from the Stonetoss website
     * and return the src URL
     */
    protected function getInfoforEntry($entry)
    {
        $return = array();
        libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $dom->validateOnParse = true;
        $dom->loadHTMLFile($entry->link());
        libxml_use_internal_errors(false);
        
        $comicElement = $dom->getElementById('comic');
        if (!is_null($comicElement))
        {
            $imgElements = $comicElement->getElementsByTagName('img');
            $img = $imgElements->item(0);

            if (!is_null($img))
            {
                $return['src'] = $img->getAttribute('src');
                $return['title'] = $img->getAttribute('title');
                $return['alt'] = $img->getAttribute('alt');
            }
        }

        return $return;
    }


    /*
     * I don't know why but Stonetoss seems to have bad UTF-8 character encoding
     * This fixes it.
     */
    protected function fixUTF8(string $input)
    {
        $output = str_replace("â€œ",'“',$input);
    }

    /**
     * Embed the comic image into the entry, if the feed is from Stonetoss
     * AND the image can be found in the origin sites content.
     *
     * @param FreshRSS_Entry $entry
     * @return mixed
     */
    public function embedStonetoss($entry)
    {
        if (!$this->supports($entry)) {
            return $entry;
        }

        $originalHash = $entry->hash();

        $imgInfo = $this->getInfoforEntry($entry);
        $new_entry = $this->replaceThumbnailInEntry($entry,$imgInfo);

        #update title
        #$entry->_title($entry->title() . ' &mdash; ' . $imgInfo['title']);

        #update image
        #$entry->_content('<h1>'.$imgInfo['title'].'</h1>'.$new_entry->saveHTML());
        $entry->_content($new_entry->saveHTML());

        $entry->_hash($originalHash);

        return $entry;
    }
}
