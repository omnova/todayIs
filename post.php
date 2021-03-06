<?php
class Post{ 
    public $body;

    private $debugMode;
    private $authors = array();

    public static $NUKED_TEXT = "MULTISYNC FILTER WOOOP WOOOOP. \n I CANNOT SHOW YOU THIS TEXT. WOOOOP\n";

    public function __construct($body){
        $this->body = $body;
        $this->debugMode = False;
    }

    public function encodePost(){
        return urlencode($this->body);
    }

    public function __toString(){
        return get_class($this);
    }

    public function setDebug(){
        $this->debugMode = True;
    }

    public function getAuthors(){
        return $this->authors;
    }

    protected function setAuthors($authors){
        $this->authors = $authors;
    }

    public function ord_suf($value){
        if(substr($value, -2, 2) == 11 || substr($value, -2, 2) == 12 || substr($value, -2, 2) == 13){
            $suffix = "th";
        }
        else if (substr($value, -1, 1) == 1){
            $suffix = "st";
        }
        else if (substr($value, -1, 1) == 2){
            $suffix = "nd";
        }
        else if (substr($value, -1, 1) == 3){
            $suffix = "rd";
        }
        else {
            $suffix = "th";
        }
        return $value . $suffix;
    }

    protected function curlData($url){
        $ch = curl_init();
        $useragent="Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20080704/3.0.0.1";

        // Set some standard cURL options
        curl_setopt($ch, CURLOPT_HEADER, 0); //important, turn off header
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_URL, $url);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /**
     * @deprecated BROKEN currently in nuShack
     */
    protected function getCategory($id) {
        //$parser = new ThreadParser();
        //$thread = $parser->getThread($id);
        //return $thread['category'];
        return "none";
    }

    protected function isNWS($id) {
        //catch possible nuked nws posts
        try {
            return (self::getCategory($id) === 'nws') ? true : false;
        } catch (Exception $e) {
            return false;
        }
    }


    protected function isNuked($id) {
        try {
            return (self::getCategory($id) === 'nuked') ? true : false;
        } catch (Exception $e) {
            //probably a nuked post, but double-check
            $page_data = trim(self::curlData('http://www.shacknews.com/laryn.x?id={$id}'));
            if(strcmp($page_data, "Bad id.")) {
                return true;
            }
            return false;
        }
    }

    protected function findtag ($comment = ""){
    //TODO: add code tag support
    $cmt = $comment;
    $i = 0;
    $stack = array ();
    $tag = "";
    $out= "";

    while ($i < strlen($cmt)) {
        if ($cmt{$i} == '<') {
            $i++;
            if ($cmt{$i} != "/") {
                $tagbody = substr ($cmt, $i, 25);
                $token = strtok ($tagbody, " =\"<>");

                if ($token == "i") {
                    $out .= "/[";
                }
                else if ($token == "b") {
                    $out .= "*[";
                }
                else if ($token == "u") {
                    $out .= "_[";
                }
                else if ($token == "span") {
                    $token = strtok (" =\"<>"); //CLASS
                    $token = strtok (" =\"<>"); //tag type

                    if ($token == "jt_blue") {
                        array_push ($stack, "}b");
                        $out .= "b{";
                    } else if ($token == "jt_red") {
                        array_push ($stack, "}r");
                        $out .= "r{";
                    } else if ($token == "jt_green") {
                        array_push ($stack, "}g");
                        $out .= "g{";
                    } else if ($token == "jt_yellow") {
                        array_push ($stack, "}y");
                        $out .= "y{";
                    } else if ($token == "jt_sample") {
                        array_push ($stack, "]s");
                        $out .= "s[";
                    } else if ($token == "jt_spoiler") {
                        array_push ($stack, "]o");
                        $out .= "o[";
                    } else if ($token == "jt_strike") {
                        array_push ($stack, "]-");
                        $out .= "-[";
                    } else if ($token == "jt_lime") {
                        array_push ($stack, "]l");
                        $out .= "l[";
                    } else if ($token == "jt_pink") {
                        array_push ($stack, "]p");
                        $out .= "p[";
                    } else if ($token == "jt_orange") {
                        array_push ($stack, "]n");
                        $out .= "n[";
                    } else if ($token == "jt_fuchsia") {
                        array_push ($stack, "]f");
                        $out .= "f[";
                    } else if ($token == "jt_olive") {
                        array_push ($stack, "]e");
                        $out .= "e[";
                    } else if ($token == "jt_quote") {
                        array_push ($stack, "]q");
                        $out .= "q[";
                    }
                } 
            } else { // it's a /closing tag
                $tagbody = substr ($cmt, $i, 10);
                $token = strtok ($tagbody, " =\"<>/");

                if ($token == "b") {
                    $out .= "]*";
                } else if ($token == "i") {
                    $out .= "]/";
                } else if ($token == "u") {
                    $out .= "]_";
                } else if ($token == "span") {
                    $out .= array_pop ($stack);
                }
            }

            //fast foward to end of tag
            $mark = $i;
            while ($cmt{$i++} != '>'){
                //ghetto code to stop unclosed < at of line
                if($i >= strlen($cmt)) {
                    $out .= $cmt{$mark-1};
                    $i = $mark;
                    break;
                }
            }
        } else {
            $out .= $cmt{$i};
            $i++;
        }
    }
    return $out;
    }
}
?>
