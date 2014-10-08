<?php
/**
 * @file
 * Contains HtmlParser class.
 * 
 * Extract the information from a HTML.
 * 
 * cf.
 * @endlink https://gist.github.com/marktheunissen/2596787  (by Mark Theunissen) @endlink
 */

require_once drupal_get_path('module', 'querypath') . '/QueryPath/QueryPath.php';
// Include QueryPath.  Assuming querypath module is installed in Drupal.

/**
 * Parses the HTML with QueryPath and retains the result.
 */
class HtmlParser {

  const LangSwitchingAnchorIn = 'language-switching-anchor';
  // ID for <a href=...> for the anchor to a document in another language.

  protected $id;
  protected $html;
  protected $qp;

  /**
   * Constructor.
   *
   * @param $id
   *   The filename, e.g. info/uk/pm7205.html
   * @param $html
   *   The full HTML text as loaded from the file.
   */
  public function __construct($id, $html) {
    $this->id = $id;
    $this->html = $html;

    $this->initQP();
    $this->setHtmlLang();	// Estimate and set the language of the HTML.
    $this->importMeta();	// Get and set the META data of the HTML.
    $this->importAltLink();	// Import Link (rel="alternate") information.
    $this->adjustTitle() 	// Set title (<h1> if exists) and title_orig.
         ->replaceLangSwitcher();	// Effective for only special cases.
  }

  /**
   * Creates the QueryPath object.
   */
  protected function initQP() {
    $qp_options = array(
      'convert_to_encoding'   => 'utf-8',
      'convert_from_encoding' => 'utf-8',
      'strip_low_ascii' => FALSE,
    );
    $this->qp = htmlqp($this->html, NULL, $qp_options);

    return $this;
  }

  /**
   * Gets the Language code and sets $this->lang_html .
   */
  protected function setHtmlLang() {
    $this->lang_html = 'ja';	// Default
    $this->qp->top('html');
    if ($this->qp->attr('lang') == ''){ 
      // <html lang="**"> is not set, hence judges based on the path (filename).
      $basefilename = basename($this->id);
      if (strpos($basefilename,'.jis') !== false) {
        $this->lang_html = 'ja';
      }
      elseif (strpos($basefilename,'.ja') !== false) {
        $this->lang_html = 'ja';
      }
      elseif (strpos($basefilename,'.en') !== false) {
        $this->lang_html = 'en';
      }
      else {
        $this->lang_html = 'ja';	// Default
      }
    }
    else {
      // <html lang="**"> is set.
      $this->lang_html = $this->qp->attr('lang');
    }

    $this->qp->top();	// Rewind.

    return $this;
  }		// protected function setHtmlLang() {


  /**
   * Gets META data of keywords and description.
   */
  protected function importMeta() {

    //
    // ** Keywords
    //
    $this->qp->top();
    $arkey = array();
    while (TRUE) {
      $this->qp->find('meta[name="keywords"]'); // [lang="en"] etc is ignored.;
      // Attribute names are case-sensitive in QueryPath!!!
      // cf. https://github.com/technosophos/querypath/blob/master/src/QueryPath/CSS/DOMTraverser/Util.php

      $c = $this->qp->attr('content');
      // if ($qp->count() == 0){ break; }	// count() undefined...
      // if (! ($qp->hasAttr('content'))){ break; }	// Somehow it does not work!
      if ($c == '') {
        break;
      }
      $arkey = array_merge($arkey, preg_split("/[\s,]+/", $c) );
      $this->qp->next();
    }
    $this->metatag_keywords = implode(", ", $arkey);
    // A comma-separated list of keywords about the page.  (said Metatag module)

    //
    // ** Description
    //
    $this->qp->top();
    $this->qp->find('meta[name="description"]'); // [lang="en"] etc is ignored.;
    $this->metatag_description = trim( $this->qp->attr('description') );

    $this->qp->top();

    return $this;
  }		// protected function importMeta() {


  /**
   * Imports Link (rel="alternate") information.
   *
   * In the HTML header, some have link tags like
   * <link rel="alternate" title="melma" href="http://example.com/test.html">
   * which indicates the alternative URI for the page.  Here those page information
   * are extracted, so as to be stored in Drupal later with the link modeule.
   */
  protected function importAltLink() {

    $this->qp->top();
    $hslink = array();	// Associated array of  Title => Uri  as a temporary variable.

    while (TRUE) {
      $this->qp->find('link[rel="alternate"]'); // [lang="ja"];
      // Attribute names are case-sensitive in QueryPath!!!
      // cf. https://github.com/technosophos/querypath/blob/master/src/QueryPath/CSS/DOMTraverser/Util.php

      $title = $this->qp->attr('title');
      $href  = $this->qp->attr('href');
      if ($href == ''){
        break;
      }

      // Determines the title (for Drupal Link module) for my case.
      if     (strpos($title,'めろんぱん') !== false) {
        $hslink['melonpan'] = $href;
      }
      elseif (strpos($title,'melma') !== false) {
        $hslink['melma']    = $href;
      }
      elseif (strpos($title,'RanSta') !== false) {
        $hslink['ransta']   = $href;
      }
      elseif (strpos($title,'まぐまぐ') !== false) {
        $hslink['mag2']     = $href;
      }
      elseif (strpos($href, 'melonpan') !== false) {
        $hslink['melonpan'] = $href;
      }
      elseif (strpos($href, 'melma') !== false) {
        $hslink['melma']    = $href;
      }
      elseif (strpos($href, 'ransta') !== false) {
        $hslink['ransta']   = $href;
      }
      elseif (strpos($href, 'mag2') !== false) {
        $hslink['mag2']     = $href;
      }
      else {
        break;
      }

      $this->qp->next();
    }

    $this->header_link_keys = array_keys($hslink);	// melonpan, mag2 etc
    $this->header_links     = array_values($hslink);	// "http://..."

    $this->qp->top();

    return $this;
  }		// protected function importAltLink() {


  /**
   * Sets title, taken from either <h1> or <title>.
   *
   * And preserves <title> as title_orig.
   * If neither exists, the filename is set for the former.
   * (The first) <h1> is removed from the content.
   */
  protected function adjustTitle() {

    $titletext = $this->qp->top('title')->text();
    $h1text    = $this->qp->top('h1')->remove()->text();	// Removed.

    if (!($h1text == NULL)) {
      $this->title      = $h1text;	// title taken from <h1> if exists.
      $this->title_orig = $titletext;	// <title> stored in title_orig.
    }
    elseif (!($titletext == NULL)) {
      // No <h1>.
      $this->title      = $titletext;
      $this->title_orig = $titletext;
    }
    else {
	  // Neither h1 nor title tag exists.
      // Hence title taken from the full-path filename minus the base directory.
      $this->title = $this->id;
      $this->title_orig = NULL;		// No <title> tag.
    }

    $this->qp->top();

    return $this;
  }		// protected function adjustTitle() {


  /**
   * Replaces the embedded language switcher.
   *
   * This function had better be called in the end of the sequence
   * (before getBody()), and setHtmlLang() must be called before this.
   *
   * The idea is to replace the "href" attribute of <a> tag that refers to
   * an URI for the different language from itself.  An example anchor is like
   * <a href="foo.en.html"> embedded in "/info/foo.jis.html".
   * In the imported Drupal system, 
   *   <a href="/en/info/foo.en.html">
   * should be the right one (that is how the Drupal language-switcher does,
   * providing the i18n preference in the site is set as such).
   * In other words, the relative path must be replaced with the absolute path.
   *
   * As this method knows the absolute path (to the document root)
   * of the local file of itself, it is possible to guess the absolute path
   * for the href file, too.  However, once the absolute path is hard-coded
   * in the HTML, it can break down in the future, if the imported system changes
   * its structure, such as, moving everything into a sub-directory.
   * Anyway, to cite the absolute path by hard-coding is not the portable way.
   *
   * A way to make it portable is to add the href attribute on the fly.
   * That is, to embed a PHP code that derives the absolute path for "href",
   * based on the current path.  Then, as long as the structure of
   * the relative paths is preserved, the link will always work.
   *
   * Unfortunately, QueryPath does not seem to be capable of replacing a part of
   * "<a href=...>" with a PHP code of "<?php ... ?>", as far as I know.
   * Hence that part is processed (by directly handling the HTML) in
   *   function modifyLangSwitchingAnchorInBody($html)
   * which is called from getBody() method.
   * In this method, only when href points to the absolute (internal) path,
   * it is modified.
   *
   * Note it is also possible to embed the PHP code to find the node-Id
   * of the translation registered in the database if exists.
   *
   * In our case, the judgement of whether the link is for the different
   * languages or not is based on the suffix of the HTML file.
   * That is, nodes are in default in Japanese, and those contains ".en."
   * are in English.
   * If the language-switcher is identified, the id attribute is added
   * with the value defined in the constant LangSwitchingAnchorIn
   * (see function modifyLangSwitchingAnchorInBody()).
   *
   * @return string
   *   $this
   */
  protected function replaceLangSwitcher() {

    // Determines the language of the anchor that is to be modified.
    if ($this->lang_html == "en") {
      $other = "ja";
    }
    else {
      $other = "en";
    }

    $this->qp->top();
    while (TRUE) {
      $this->qp->find('a[href]');
      $href = $this->qp->attr('href');
      if ($href == ''){
        // End of file (Essential to break out of the loop.)
        break;
      }
      elseif (preg_match('@^[^/]+://@', $href)) {
        // External link (http, ftp, file, ...)
        $this->qp->next();
        continue;
      }
      elseif (preg_match('@\.$other\.@', $href)) {
        // "href" is in a different language, judging from the filename.

        if (preg_match('@^/@', $href)) {
          $this->qp->attr('href', preg_replace('@^/(en|ja)/)?@', '/' . $other . '/', $href));
          $this->qp->attr('id', self::LangSwitchingAnchorIn);
          // Absolute path is used.  Simply the href attribute is modified.
          // Plus, an id attribute is added.
        }
      }

      $this->qp->next();
    }	// while (TRUE) {

    return $this;
  }		// protected function replaceLangSwitcher() {


  /**
   * Modify the anchors to another language from HTML to PHP.
   *
   * This function is supposed to be called from function getBody(),
   * though this function should work for any input text of HTML.
   *
   * @param string
   *   Html body text.
   * @return string
   *   HTML body text, perhaps modified.
   * @see replaceLangSwitcher() for detail.
   */
  protected function modifyLangSwitchingAnchorInBody($html) {

    $str_anchor_id = sprintf('id="%s"', self::LangSwitchingAnchorIn);

    // Get the language code, different from the current HTML.
    if ($this->lang_html == "en") {
      $other = "ja";
      $other_rex = "(?:j[ap]\.)?jis";
    }
    else {
      $other = "en";
      $other_rex = "en(?:\.us)?";
    }

    $ret = preg_replace_callback(
      '@(<a\s+[^>]*href=([\'"]))([^"\'>/][^>\s]*\.' . $other_rex . '\.[^>\s]+)(\2\s*(|\s[^>]+))>@im',	// Absolute paths excluded.
      function($matched) use ($other, $str_anchor_id) {
        $href = $matched[3]; // printf("href=%s\n",$href);
        if (preg_match('@^[^/]+://@', $href)) {	// http, ftp, file, etc
          // http, ftp, file, etc. => Do nothing.
          return $matched[0];
        }
        else {
          // Relative path.
          $phpcode = "<?php \$uri = sprintf('/".$other."/%s/".$href."', preg_replace('@^(ja|en)/@', '', dirname(request_path()))); ";
          // Writes the PHP code to get the absolute path minus language prefix
          $origescaped = str_replace("'", '"', $matched[0]);
          $phpcode .= "printf('" . preg_replace('@^(.+href=)"[^\s">]+"(.+)@im', '\1"%s" ' . $str_anchor_id . '\2', $origescaped) . "', \$uri); ?>";
          // PHP code to replace the href with the modified absolute path.

          return $phpcode;
        }
      },
      $html
    );

    return $ret;
  }		// protected function modifyLangSwitchingAnchorInBody($html) {


  /**
   * Returns the HTML body.
   *
   * @return string
   *   HTML body text.
   */
  public function getBody() {
    $body = $this->qp->top('body')->innerHTML();
    $body = trim($body);

    $body = $this->modifyLangSwitchingAnchorInBody($body);
    // Modify the anchors to another language from HTML to PHP.
    $body = preg_replace('/(\W\w{1,5})\w*(@\w)\w*(?:\.\w+)*\.(?:com|org|net|edu|uk|fr|de|es|jp)(\W)/', '$1&hellip;$2&hellip;$3', $body);
    // Truncates email addresses.

    return $body;
  }

}

