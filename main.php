<?php
require 'simple_html_dom.php';
require 'post.php';
require 'birthdayPost.php';
require 'infPost.php';
require 'lolPost.php';
require 'tagPost.php';
require 'unfPost.php';
require 'randomPost.php';
require 'awardPost.php';

class PostBot{ 
    public $username;
    public $password;

    public $parentId;
    public $groupId;

    private $posts = array();
    private $sleeptime;
    private $debugMode;
    private $latestUrl = 'http://www.shacknews.com/latestchatty.x';
    private $postUrl = 'http://www.shacknews.com/extras/post_laryn_iphone.x';

    public function __construct($username, $password, $sleep, $debug) {
        $this->username = $username;
        $this->password = $password;
        $this->sleeptime = $sleep;
        $this->debugMode = $debug;
    }

    public function setLatestChattyUrl() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, "Firefox 5.0");
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_URL, $this->latestUrl);
        $result = curl_exec($ch);

        //pull last 5 digits of latest chatty URL
        $groupTemp = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        //set the group on the post
        $this->groupId = substr($groupTemp, strlen($groupTemp)-5, strlen($groupTemp));

        curl_close($ch);
    }

    public function setRootPost() {
        $p = new Post('');
        $dayth = $p->ord_suf(date('z')+1);
        $body = "*[y{Today is ".date('l\, \t\h\e jS \o\f F').", the {$dayth} day of ".date('Y').".}y]*\n";

        //TODO add custom today is items
        $cdate = mktime(0, 0, 0, 8, 13, 2009, 0);
        $today = time();
        $difference = $cdate - $today;
        if ($difference > 0) { 
            $body .= "There are /[OMG]/ ".floor($difference/60/60/24)." days until Quakecon!!!!\n";
        } elseif ($difference == 0) {
            $body .= "HOLY SHIT IT'S QUAKECON TIME";
        }

        //TODO create quote database to use here
        //$body .= "This is your life shackers, enjoy it.";
        $body .= system("curl -Is slashdot.org | egrep '^X-(F|B|L)' | sed s/^X-//");

        $p->body = $body;
        //make first post
        self::post($p);

        //get the latest chatty and parse for the last post by my username...
        //$dom = file_get_dom("http://chatty.elrepositorio.com/{$this->groupId}.xml");
        //TODO check for empty groupid
        $dom = file_get_dom("http://shackchatty.com/{$this->groupId}.xml");
        $v = $dom->find("comment[author={$this->username}]",0);

        #TODO if no parent id, stop posting and email error
        $this->parentId = $v->id;

        //Post URL to API
        if(!$this->debugMode) {
            shell_exec("echo {$v->id} > /home/askedrelic/public_html/asktherelic.com/public/shack/todayis.txt");
        }
    }

    public function addPost($post) {
        //add a post to the pool
        array_push($this->posts, $post);
    }

    public function makePosts() {
        //loop through all posts and post em!
        foreach($this->posts as $p) {
            sleep($this->sleeptime);
            self::post($p);
        }
        self::generateAwards();
    }

    public function generateAwards() {
        $awardPost = new AwardPost($this->posts);

        if($awardPost->checkAwardWinner()) {
            print "THERE ARE AWARDS!\n";
            self::post($awardPost);
        }
    }

    private function post($post) {
        //    * iuser: username
        //    * ipass: password
        //    * parent: The ID of the post that is being replied to. Leave blank it its a new root post.
        //    * group: The story ID this post is getting attached to.
        //    * body: The text content of the comment.
        $body = $post->encodePost();
        $fields = 'iuser='.urlencode($this->username);
        $fields .= '&ipass='.urlencode($this->password);
        $fields .= '&parent='.urlencode($this->parentId);
        $fields .= '&group='.urlencode($this->groupId);
        $fields .= '&body='.$post->encodePost();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, "Firefox 5.0");
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_URL, $this->postUrl);
        if(!$this->debugMode) {
            $result = curl_exec($ch);
        } else {
            echo "post ---------------\n";
            // $post->setDebug();
            echo $post->body."\n\n";
            return NULL;
        }

        $result2 = "";
        //check once for PRL
        if(preg_match("/Please wait a few minutes/i", $result)){
            sleep(360);
            $result2 = curl_exec($ch);
        }

        //check twice for PRL
        if(preg_match("/Please wait a few minutes/i", $result2)){
            sleep(360);
            curl_exec($ch);
        }

        curl_close($ch);
    }
}

$a = new PostBot('askedrelic','xXxXxXxXxXx', 90, False);
$a->setLatestChattyUrl();
$a->setRootPost();

$a->addPost(new BirthdayPost());
$a->addPost(new LolPost());
$a->addPost(new TagPost());
$a->addPost(new UnfPost());
$a->addPost(new InfPost());
//$a->addPost(new RandomPost());

$a->makePosts();
?>
