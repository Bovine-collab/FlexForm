<?php

use WSForm\Core\Core;

/**
 * Class TagHooks
 *
 * This class is responsible for rendering tags.
 */
class TagHooks {
    /**
     * @var ThemeStore
     */
    private $themeStore;

    /**
     * TagHooks constructor.
     *
     * @param ThemeStore $themeStore The theme store to use
     */
    public function __construct( ThemeStore $themeStore ) {
        $this->themeStore = $themeStore;
    }

    /**
     * @brief Function to render the Form itself.
     *
     * This function will call its subfunction render_form()
     * It will also add the JavaScript on the loadscript variable
     * \n Additional parameters
     * \li loadscript
     * \li showmessages
     * \li restrictions
     * \li no_submit_on_return
     * \li action
     * \li changetrigger
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array|string send to the MediaWiki Parser or send to the MediaWiki Parser with the message not a valid function
     */
    public function renderForm( $input, array $args, Parser $parser, PPFrame $frame ) {
        global $wgUser, $wgEmailConfirmToEdit, $IP, $wgScript;

        Core::$chkSums = array();
        Core::$formId = uniqid();

        // Set i18n general messages
        Core::$msg_unverified_email = wfMessage( "wsform-unverified-email1" )->text() . wfMessage( "wsform-unverified-email2" )->text();

        $ret = '';

        $parser->getOutput()->addModuleStyles( 'ext.wsForm.general.styles' );

        // Do we have some messages to show?
        if ( isset( $args['showmessages'] ) ) {
            if ( !isset ( $_COOKIE['wsform'] ) ) {
                return "";
            }

            $ret = '<div class="wsform alert-' . $_COOKIE['wsform']['type'] . '">' . $_COOKIE['wsform']['txt'] . '</div>';
            setcookie( "wsform[type]", "", time() - 3600, '/' );
            setcookie( "wsform[txt]", "", time() - 3600, '/' );

            return array( $ret, 'noparse' => true, "markerType" => 'nowiki' );
        }

        // Are there explicit 'restrictions' lifts set?
        if ( isset( $args['restrictions'] ) ) {
            // Parse the given restriction
            $restrictions = $parser->recursiveTagParse( $args['restrictions'], $frame );

            // Only allow anonymous users if the restrictions are lifted
            $allowAnonymous = strtolower( $restrictions ) === 'lifted';
        } else {
            // By default, deny anonymous users
            $allowAnonymous = false;
        }

        // TODO: Will be deprecated in 1.36. As off 1.34 use isRegistered()
        // Block the request if the user is not logged in and anonymous users are not allowed
        if ( $allowAnonymous === false && !$wgUser->isLoggedIn() ) {
            return wfMessage( "wsform-anonymous-user" )->parse();
        }

        $formId = isset( $args['id'] ) && $args['id'] !== '' ? $args['id'] : false;

        // Do we have scripts to load?
        if ( isset( $args['loadscript'] ) && $args['loadscript'] !== '' ) {
            $scriptToLoad = $args['loadscript'];

            // Validate the file name
            if ( preg_match( '^[a-zA-Z0-9_-]+$', $scriptToLoad ) === 1 ) {
                // Is this script already loaded?
                if ( !Core::isLoaded( $scriptToLoad ) ) {
                    if ( file_exists( $IP . '/extensions/WSForm/modules/customJS/loadScripts/' . $scriptToLoad . '.js' ) ) {
                        $scriptContent = file_get_contents( $IP . '/extensions/WSForm/modules/customJS/loadScripts/' . $scriptToLoad . '.js' );
                        if ( $scriptContent !== false ) {
                            if ( $formId !== false ) {
                                Core::includeJavaScriptConfig( 'wsForm_' . $scriptToLoad, $formId );
                            }

                            Core::includeInlineScript( $scriptContent );
                            Core::addAsLoaded( $scriptToLoad );
                        }
                    }
                }
            }
        }

        // TODO:

        if ( isset( $args['no_submit_on_return'] ) ) {
            if(! wsform\wsform::isLoaded('keypress') ) {
                $noEnter = "$(document).on('keyup keypress', 'form input[type=\"text\"]', function(e) {
            if(e.keyCode == 13) {
              e.preventDefault();
              return false;
            }
          });$(document).on('keyup keypress', 'form input[type=\"search\"]', function(e) {
            if(e.keyCode == 13) {
              e.preventDefault();
              return false;
            }
          });$(document).on('keyup keypress', 'form input[type=\"password\"]', function(e) {
            if(e.keyCode == 13) {
              e.preventDefault();
              return false;
            }
          })";
                wsform\wsform::includeInlineScript( $noEnter );
                wsform\wsform::addAsLoaded( 'keypress' );
            }
        }

        if ( isset( $args['action'] ) && $args['action'] == 'addToWiki' && $allowAnonymous === false ) {
            if ( $wgEmailConfirmToEdit === true && ! $wgUser->isEmailConfirmed() ) {
                $ret = wsform\wsform::$msg_unverified_email;

                return $ret;
            }
        }
        if ( isset( $args['changetrigger'] ) && $args['changetrigger'] !== '' && isset($args['id'])) {
            $onchange = "";
            $changeId = $args['id'];
            $changeCall = $args['changetrigger'];
            $onchange = "$('#" . $changeId . "').change(" . $changeCall . "(this));";
            wsform\wsform::includeInlineScript( $onchange );
        } else $onchange = false;

        if( isset( $args['messageonsuccess']) && $args['messageonsuccess'] !== '' ) {
            $msgOnSuccessJs = $js = 'var mwonsuccess = "' . $args['messageonsuccess'] . '";';
            wsform\wsform::includeInlineScript( $msgOnSuccessJs );
        } else $msgOnSuccessJs = '';

        if( isset( $args['show-on-select' ] ) ) {
            \wsform\wsform::setShowOnSelectActive();
            $input = \wsform\wsform::checkForShowOnSelectValue( $input );
        }

        $output = $parser->recursiveTagParse( $input, $frame );
        foreach ( $args as $k => $v ) {
            if ( ( strpos( $v, '{' ) !== false ) && ( strpos( $v, "}" ) !== false ) ) {
                $args[ $k ] = $parser->recursiveTagParse( $v, $frame );
            }
        }
        if (wsform\wsform::getRun() === false) {
            $realUrl = str_replace( '/index.php', '', $wgScript );
            $ret = '<script type="text/javascript" charset="UTF-8" src="' . $realUrl . '/extensions/WSForm/WSForm.general.js"></script>' . "\n";
            wsform\wsform::setRun(true);
        }
        $ret .= wsform\form\render::render_form( $args, $parser->getTitle()->getLinkURL() );

        //Add checksum

        if( \wsform\wsform::isShowOnSelectActive() ) {
            $ret .= \wsform\wsform::createHiddenField( 'showonselect', '1' );

        }

        if( \wsform\wsform::$secure ) {
            \wsform\protect\protect::setCrypt( \wsform\wsform::$checksumKey );
            if( \wsform\wsform::$runAsUser ) {
                $chcksumwuid = \wsform\protect\protect::encrypt( 'wsuid' );
                $uid = \wsform\protect\protect::encrypt( $wgUser->getId() );
                \wsform\wsform::addCheckSum( 'secure', $chcksumwuid, $uid, "all" );
                $ret          .= '<input type="hidden" name="' . $chcksumwuid . '" value="' . $uid . '">';
            }
            $chcksumName = \wsform\protect\protect::encrypt( 'checksum' );
            if( !empty( \wsform\wsform::$chkSums ) ) {
                $chcksumValue = \wsform\protect\protect::encrypt( serialize( \wsform\wsform::$chkSums ) );
                $ret          .= '<input type="hidden" name="' . $chcksumName . '" value="' . $chcksumValue . '">';
                $ret          .= '<input type="hidden" name="formid" value="' . \wsform\wsform::$formId . '">';
            }

        }




        $ret .= $output . '</form>';

        if( isset( $args['recaptcha-v3-action'] ) && ! wsform\wsform::isLoaded( 'google-captcha' ) ) {
            $tmpCap = wsform\recaptcha\render::render_reCaptcha();
            if( $tmpCap !== false ) {
                wsform\wsform::addAsLoaded( 'google-captcha' );
                $ret = $tmpCap . $ret;
            }
        }

        if( wsform\wsform::$reCaptcha !== false  ) {
            if( !isset( $args['id']) || $args['id'] === '' ) {
                $ret = wfMessage( "wsform-recaptcha-no-form-id" )->text();
                return $ret;
            }
            if ( file_exists( $IP . '/extensions/WSForm/modules/recaptcha.js' ) ) {
                $rcaptcha = file_get_contents( $IP . '/extensions/WSForm/modules/recaptcha.js' );
                $replace = array(
                    '%%id%%',
                    '%%action%%',
                    '%%sitekey%%',
                );
                $with = array(
                    $args['id'],
                    wsform\wsform::$reCaptcha,
                    wsform\recaptcha\render::$rc_site_key
                );
                $rcaptcha = str_replace( $replace, $with, $rcaptcha );
                wsform\wsform::includeInlineScript( $rcaptcha );
                wsform\wsform::$reCaptcha = false;
            } else {
                $ret = wfMessage( "wsform-recaptcha-no-js" )->text();
                return $ret;
            }
        }
        //echo "<pre>";
        // print_r( \wsform\wsform::$chkSums );
        // echo "</pre>";
        //print_r( \wsform\wsform::$secure );
        //print_r( wsform\wsform::getJavaScriptConfigToBeAdded() );

        //echo "<pre>";
        //print_r( wsform\wsform::getJavaScriptConfigToBeAdded() ) ;
        //echo "</pre>";
        self::addInlineJavaScriptAndCSS();
        return array( $ret, "markerType" => 'nowiki' );

    }

    /**
     * @brief Function to render an input field.
     *
     * This function will look for the type of input field and will call its subfunction render_<inputfield>
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array send to the MediaWiki Parser
     */
    public function renderField( $input, array $args, Parser $parser, PPFrame $frame ) {
        if ( isset( $args['type'] ) ) {
            $type = $args['type'];

            if ( wsform\validate\validate::validInputTypes( $type ) ) {
                $parsePost = false;
                if( isset( $args['parsepost'] ) && isset( $args['name'] )) {
                    $parsePost = true;
                    $parseName = $args['name'];
                    unset( $args['parsepost'] );
                }
                $type = "render_" . $type;
                unset( $args['type'] );
                $noParse = false;
                if ( method_exists( 'wsform\field\render', $type ) ) {

                    foreach ( $args as $k => $v ) {
                        if ( ( strpos( $v, '{' ) !== false ) && ( strpos( $v, '}' ) !== false ) ) {
                            $args[ $k ] = $parser->recursiveTagParse( $v, $frame );
                        }
                        if( $k === 'noparse' ) {
                            $noParse = true;
                        }
                    }

                    //Test to see if this gets parsed
                    if( $noParse === false ) {
                        $input = $parser->recursiveTagParse($input, $frame);
                    }
                    //End test
                    if ( $type == 'render_option' || $type == 'render_file' || $type == 'render_submit' || $type == 'render_text' || $type == 'render_textarea') {
                        $ret = wsform\field\render::$type( $args, $input, $parser, $frame );
                    } else {
                        $ret = wsform\field\render::$type( $args, $input );
                    }
                } else {
                    $ret = $type . " is unknown";
                }

                if( $parsePost === true ) {
                    $ret .= '<input type="hidden" name="wsparsepost[]" value="' . $parseName . "\">\n";
                }
                //self::addInlineJavaScriptAndCSS();

                return array( $ret, "markerType" => 'nowiki');
            } else return array( wfMessage( "wsform-field-invalid" )->text() . ": " . $type, "markerType" => 'nowiki');
        } else {
            return array( wfMessage( "wsform-field-invalid" )->text(), "markerType" => 'nowiki');
        }

    }

    /**
     * @brief This is the initial call from the MediaWiki parser for the WSFieldset
     *
     * @param string $input Received from parser from begin till end
     * @param array $args List of argmuments for the Fieldset
     * @param Parser $parser MediaWiki parser
     * @param PPFrame $frame MediaWiki pframe
     *
     * @return array with full rendered html for the parser to add
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function renderFieldset( $input, array $args, Parser $parser, PPFrame $frame ) {
        $ret = '<fieldset ';
        foreach ( $args as $k => $v ) {
            if ( wsform\validate\validate::validParameters( $k ) ) {
                $ret .= $k . '="' . $v . '" ';
            }
        }
        $output = $parser->recursiveTagParse( $input, $frame );
        $ret    .= '>' . $output . '</fieldset>';
        //self::addInlineJavaScriptAndCSS();
        return array( $ret, "markerType" => 'nowiki' );
    }

    /**
     * @brief renderes the html legend (for use with fieldset)
     *
     * @param string $input Received from parser from begin till end
     * @param array $args List of argmuments for the Legend
     * @param Parser $parser MediaWiki parser
     * @param PPFrame $frame MediaWiki pframe
     *
     * @return array with full rendered html for the parser to add
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function renderLegend( $input, array $args, Parser $parser, PPFrame $frame ) {
        $ret = '<legend ';
        if ( isset( $args['class'] ) ) {
            $ret .= ' class="' . $args['class'] . '" ';
        }
        if ( isset( $args['align'] ) ) {
            $ret .= ' align="' . $args['align'] . '"';
        }
        $ret .= '>' . $input . '</legend>';
        //self::addInlineJavaScriptAndCSS();
        return array( $ret, "markerType" => 'nowiki' );

    }

    /**
     * @brief renders the html label
     *
     * @param string $input Received from parser from begin till end
     * @param array $args List of arguments for a Label
     * @param Parser $parser MediaWiki parser
     * @param PPFrame $frame MediaWiki pframe
     *
     * @return array with full rendered html for the parser to add
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function renderLabel( $input, array $args, Parser $parser, PPFrame $frame ) {
        $ret = '<label ';
        foreach ( $args as $k => $v ) {
            if ( wsform\validate\validate::validParameters( $k ) ) {
                if ( ( strpos( $v, '{' ) !== false ) && ( strpos( $v, '}' ) !== false ) ) {
                    $v = $parser->recursiveTagParse( $v, $frame );
                }
                $ret .= $k . '="' . $v . '" ';
            }
        }

        $output = $parser->recursiveTagParse( $input, $frame );
        $ret    .= '>' . $output . '</label>';
        //self::addInlineJavaScriptAndCSS();
        return array( $ret, "markerType" => 'nowiki' );

    }

    /**
     * @brief This is the initial call from the MediaWiki parser for the WSSelect
     *
     * @param $input string Received from parser from begin till end
     * @param array $args List of argmuments for the selectset
     * @param Parser $parser MediaWiki parser
     * @param PPFrame $frame MediaWiki pframe
     *
     * @return array with full rendered html for the parser to add
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function renderSelect( $input, array $args, Parser $parser, PPFrame $frame ) {
        $ret = '<select ';


        foreach ( $args as $k => $v ) {
            if ( wsform\validate\validate::validParameters( $k ) ) {
                if ( $k == "name" && strpos( $v, '[]' ) === false ) {
                    $name = $v;
                    $v    .= '[]';
                }
                $ret .= $k . '="' . $parser->recursiveTagParse( $v, $frame ) . '" ';
            }
        }
        $output = $parser->recursiveTagParse( $input, $frame );

        $ret .= '>';
        if ( isset( $args['placeholder'] ) ) {
            $ret .= '<option value="" disabled selected>' . $args['placeholder'] . '</option>';
        }
        $ret .=  $output . '</select>';

        //self::addInlineJavaScriptAndCSS();
        return array( $ret, "markerType" => 'nowiki' );

    }

    /**
     * @brief This is the initial call from the MediaWiki parser for the WSToken
     *
     * @param $input string Received from parser from begin till end
     * @param array $args List of argmuments for the Fieldset
     * @param Parser $parser MediaWiki parser
     * @param PPFrame $frame MediaWiki pframe
     *
     * @return array with full rendered html for the parser to add
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function renderToken( $input, array $args, Parser $parser, PPFrame $frame ) {
        global $wgOut, $IP, $wgDBname, $wgDBprefix;

        if( isset ( $wgDBprefix ) && !empty($wgDBprefix) ) {
            $prefix = '_' . $wgDBprefix;
        } else $prefix = '';

        //$parser->disableCache();
        //$parser->getOutput()->addModules( 'ext.wsForm.select2.kickstarter' );
        $ret         = '<select data-inputtype="ws-select2"';
        $placeholder = false;
        $allowtags = false;
        $onlyone = false;
        $multiple = false;


        foreach ( $args as $k => $v ) {
            if ( wsform\validate\validate::validParameters( $k ) ) {
                if ( $k == 'placeholder' ) {
                    $placeholder = $parser->recursiveTagParse( $v, $frame );
                } elseif( strtolower( $k ) === "multiple") {
                    $multiple = $parser->recursiveTagParse( $v, $frame );
                    if ( $multiple === "multiple" ) {
                        $ret .= 'multiple="multiple" ';
                    }
                } elseif( strtolower( $k ) === 'id' &&  \wsform\wsform::isLoaded( 'wsinstance-initiated' ) ) {
                    $ret .= 'data-wsselect2id="' . $v . '"';
                } else {
                    $ret .= $k . '="' . $parser->recursiveTagParse( $v, $frame ) . '" ';
                }
            }
        }

        $output = $parser->recursiveTagParse( $input );
        $id   = $parser->recursiveTagParse( $args['id'], $frame );

        $ret    .= '>';
        if( $placeholder !== false ){
            $ret .= '<option></option>';
        }
        $ret .= $output . '</select>' . "\n";
        $out    = "";
        if ( ! \wsform\wsform::isLoaded( 'wsinstance-initiated' ) ){
            $out    .= '<input type="hidden" id="select2options-' . $id . '" value="';
        } else {
            $out    .= '<input type="hidden" data-wsselect2options="select2options-' . $id . '" value="';
        }

        if( isset( $args['input-length-trigger'] ) && $args['input-length-trigger' !== '' ] ) {
            $iLength = trim( $args['input-length-trigger'] );
        } else $iLength = 3;

        if ( isset( $args['json'] ) && isset( $args['id'] ) ) {
            if ( strpos( $args['json'], 'semantic_ask' ) ) {
                $json = $args['json'];
            } else {
                $json = $parser->recursiveTagParse( $args['json'], $frame );
            }
            $out .= "var jsonDecoded = decodeURIComponent( '" . urlencode( $json ) . "' );\n";
        }


        $out .= "$('#" . $id . "').select2({";

        $callb = '';

        $mwdb = $wgDBname . $prefix;

        if ( $placeholder !== false ) {
            $out .= "placeholder: '" . $placeholder . "',";
        }

        if ( isset( $args['json'] ) && isset( $args['id'] ) ) {

            $out .= "\ntemplateResult: testSelect2Callback,\n";
            $out .= "\nescapeMarkup: function (markup) { return markup; },\n";
            $out .= "\nminimumInputLength: $iLength,\n";
            $out .= "\najax: { url: jsonDecoded, delay:500, dataType: 'json',"."\n";
            $out .= "\ndata: function (params) { var queryParameters = { q: params.term, mwdb: '".$mwdb."' }\n";
            $out .= "\nreturn queryParameters; }}";
            $callb= '';
            if ( isset( $args['callback'] ) ) {
                if ( isset( $args['template'] ) ) {
                    $templ = ", '" . $args['template'] . "'";
                } else $templ = '';
                $cb  = $parser->recursiveTagParse( $args['callback'], $frame );
                $callb = "$('#" . $id . "').on('select2:select', function(e) { " . $cb . "('" . $id . "'" . $templ . ")});\n";
                $callb .= "$('#" . $id . "').on('select2:unselect', function(e) { " . $cb . "('" . $id . "'" . $templ . ")});\n";
            }
        }
        if( isset( $args['allowtags'] ) ) {
            if ( isset( $args['json'] ) && isset( $args['id'] ) ) {
                $out .= ",\ntags: true";
            } else {
                $out .= "\ntags: true";
            }
        }
        if( isset( $args['allowclear'] ) && isset( $args['placeholder'] ) ) {
            if ( ( isset( $args['json'] ) ) || isset( $args['allowtags'] ) ) {
                $out .= ",\nallowClear: true";
            } else {
                $out .= "\nallowClear: true";
            }
        }

        /*
                if( $multiple !== false && strtolower( $multiple ) === "multiple" ) {

                    if ( ( isset( $args['json'] ) && isset( $args['id'] ) ) || isset( $args['allowtags'] ) || isset( $args['allowclear'] ) ) {
                        $out .= ",\nmultiple: true";
                    } else {
                        $out .= "\nmultiple: true";
                    }
                } else {
                    if ( ( isset( $args['json'] ) && isset( $args['id'] ) ) || isset( $args['allowtags'] ) || isset( $args['allowclear'] ) ) {
                        $out .= ",\nmultiple: false";
                    } else {
                        $out .= "\nmultiple: false";
                    }
                }
        */
        $out .= '});';
        $callb .= "$('select').trigger('change');\"\n";
        $out .= $callb . ' />';
        $lcallback = '';
        if(isset($args['loadcallback'])) {
            if(! wsform\wsform::isLoaded($args['loadcallback'] ) ) {
                if ( file_exists( $IP . '/extensions/WSForm/modules/customJS/wstoken/' . $args['callback'] . '.js' ) ) {
                    $lf  = file_get_contents( $IP . '/extensions/WSForm/modules/customJS/wstoken/' . $args['callback'] . '.js' );
                    $lcallback = "<script>$lf</script>\n";
                    wsform\wsform::includeInlineScript( $lf );
                    wsform\wsform::addAsLoaded( $args['loadcallback'] );
                }
            }
        }
        $attach = "<script>wachtff(attachTokens, true );</script>";
        //wsform\wsform::includeInlineScript( 'document.addEventListener("DOMContentLoaded", function() { wachtff(attachTokens, true); }, false);' );
        //$wgOut->addHTML( $out );

        $ret = $ret . $out . $attach;
        self::addInlineJavaScriptAndCSS();
        return array( $ret, "markerType" => 'nowiki' );
    }

    /**
     * @brief Function to render the Page Edit options.
     *
     * This function will call its subfunction render_edit()
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array send to the MediaWiki Parser
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function renderEdit( $input, array $args, Parser $parser, PPFrame $frame ) {
        foreach ( $args as $k => $v ) {
            if ( ( strpos( $v, '{' ) !== false ) && ( strpos( $v, "}" ) !== false ) ) {
                $args[ $k ] = $parser->recursiveTagParse( $v, $frame );
            }
        }

        $ret = wsform\edit\render::render_edit( $args );
        //self::addInlineJavaScriptAndCSS();
        return array( $ret, 'noparse' => true, "markerType" => 'nowiki' );
    }

    /**
     * @brief Function to render the Page Create options.
     *
     * This function will call its subfunction render_create()
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array send to the MediaWiki Parser
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function renderCreate( $input, array $args, Parser $parser, PPFrame $frame ) {
        foreach ( $args as $k => $v ) {
            if ( ( strpos( $v, '{' ) !== false ) && ( strpos( $v, "}" ) !== false ) ) {
                $args[ $k ] = $parser->recursiveTagParse( $v, $frame );
            }
        }
        $ret = wsform\create\render::render_create( $args );
        //self::addInlineJavaScriptAndCSS();
        return array( $ret, 'noparse' => true, "markerType" => 'nowiki' );

    }

    /**
     * @brief Function to render the email options.
     *
     * This function will call its subfunction render_mail()
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array send to the MediaWiki Parser or
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function renderEmail( $input, array $args, Parser $parser, PPFrame $frame ) {
        $args['content'] = base64_encode( $parser->recursiveTagParse( $input, $frame ) );
        foreach ( $args as $k => $v ) {
            if ( ( strpos( $v, '{' ) !== false ) && ( strpos( $v, "}" ) !== false ) ) {
                $args[ $k ] = $parser->recursiveTagParse( $v, $frame );
            }
        }
        $ret = wsform\mail\render::render_mail( $args );
        //self::addInlineJavaScriptAndCSS();
        return array( $ret, 'noparse' => true, "markerType" => 'nowiki' );
    }

    /**
     * @brief Function to render a WSInstance.
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array send to the MediaWiki Parser or
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function renderInstance( $input, array $args, Parser $parser, PPFrame $frame ) {
        global $IP, $wgScript;
        $realUrl = str_replace( '/index.php', '', $wgScript );


        // Add move, delete and add button with classes
        $parser->getOutput()->addModuleStyles( 'ext.wsForm.Instance.styles' );

        if( ! \wsform\wsform::isLoaded( 'wsinstance-initiated' ) ) {
            wsform\wsform::addAsLoaded( 'wsinstance-initiated' );
        }

        $output = $parser->recursiveTagParse( $input, $frame );

        if( ! \wsform\wsform::isLoaded( 'wsinstance-initiated' ) ) {
            wsform\wsform::addAsLoaded( 'wsinstance-initiated' );
        }

        $ret = wsform\instance\render::render_instance( $args, $output );

        wsform\wsform::removeAsLoaded( 'wsinstance-initiated' );

        if(! wsform\wsform::isLoaded( 'multipleinstance' ) ) {
            if ( file_exists( $IP . '/extensions/WSForm/modules/instances/wsInstance.js' ) ) {
                $ls =  $realUrl . '/extensions/WSForm/modules/instances/wsInstance.js';
                $ret = '<script type="text/javascript" charset="UTF-8" src="' . $ls . '"></script>' . $ret ;
                //wsform\wsform::includeInlineScript( $ls );
                //$parser->getOutput()->addModules( ['ext.wsForm.instance'] );
                wsform\wsform::addAsLoaded( 'multipleinstance' );
            }
        }





        return array( $ret, 'noparse' => true, "markerType" => 'nowiki' );
    }

    /**
     * Parses the given arguments.
     *
     * @param array $arguments
     * @param Parser $parser
     * @param PPFrame $frame
     *
     * @return array
     */
    private static function parseArguments( array $arguments, Parser $parser, PPFrame $frame ) {
        $result = [];

        foreach ( $arguments as $name => $value ) {
            if ( ( strpos( $value, '{' ) !== false ) && ( strpos( $value, '}' ) !== false ) ) {
                $result[$name] = $parser->recursiveTagParse( $value, $frame );
            }
        }

        return $result;
    }

    /**
     * Parses the given input.
     *
     * @param string $input
     * @param Parser $parser
     * @param PPFrame $frame
     *
     * @return string
     */
    private static function parseInput( string $input, Parser $parser, PPFrame $frame ) {
        return $parser->recursiveTagParseFully( $input, $frame );
    }

    /**
     * Converts an array of values in form [0] => "name=value" into a real
     * associative array in form [name] => value. If no = is provided,
     * true is assumed like this: [name] => true
     *
     * @param array $options
     *
     * @return array $results
     */
    public function extractOptions( array $options ) {
        $results = array();
        foreach ( $options as $option ) {
            $pair = explode( '=', $option, 2 );
            if ( count( $pair ) === 2 ) {
                $name             = trim( $pair[0] );
                $value            = trim( $pair[1] );
                $results[ $name ] = $value;
            }

            if ( count( $pair ) === 1 ) {
                $name             = trim( $pair[0] );
                $results[ $name ] = true;
            }
        }
        return $results;
    }

    private function addInlineJavaScriptAndCSS( $parentConfig = false ) {
        $scripts = array_unique( Core::getJavaScriptToBeIncluded() );
        $csss = array_unique( Core::getCSSToBeIncluded() );
        $jsconfigs = Core::getJavaScriptConfigToBeAdded();
        $out = \RequestContext::getMain()->getOutput();

        if( !empty( $scripts ) ) {
            foreach ( $scripts as $js ) {
                $out->addInlineScript( $js );
            }

            Core::cleanJavaScriptList();
        }

        if( !empty( $csss ) ) {
            foreach ( $csss as $css ) {
                $out->addInlineStyle( $css );
            }

            Core::cleanCSSList();
        }

        if( !empty( $jsconfigs ) ) {
            if( $parentConfig ) {
                $out->addJsConfigVars( array( $jsconfigs ) );
            } else {
                $out->addJsConfigVars( array( 'wsformConfigVars' => $jsconfigs ) );
            }

            Core::cleanJavaScriptConfigVars();
        }
    }
}