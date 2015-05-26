<?php
/**
 * Created by PhpStorm.
 * User: michaeldu
 * Date: 5/26/15
 * Time: 8:07 AM
 */

namespace frontend\controllers;

use frontend\models\PublishForm;
use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use frontend\models\SignupForm;
use common\models\LoginForm;
use yii\filters\AccessControl;


class RedisController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'signup', 'logout', 'login', 'index', 'follow', 'my-following', 'my-followers', 'space'
                ],
            ],
        ];
    }

    /**
     * 用户注册
     * @return string|\yii\web\Response
     */
    public function actionSignup()
    {
        $model = new SignupForm();
        if (Yii::$app->request->isPost) {
            $SignupForm = Yii::$app->request->post("SignupForm");
            $email = $SignupForm['email'];
            $password = $SignupForm['password'];
            $username = $SignupForm['username'];
            $created_at = time();
            $followings = 0;
            $fans = 0;
            $posts = 0;

            //检查邮箱是否唯一
            if ( Yii::$app->redis->hexists("email.to.id", $email) ) {
                Yii::$app->session->setFlash("failure", "该邮箱已被注册");

                return $this->render('signup', [
                    'model' => $model,
                ]);
            }

            $userID = Yii::$app->redis->incr("users:count");
            Yii::$app->redis->hmset("user:{$userID}", "email", $email, "password", md5($password), "username", $username, "followings", $followings, "fans", $fans, "posts", $posts, "created_at", $created_at);
            Yii::$app->redis->hset("email.to.id", $email, $userID);

            Yii::$app->session->set("userid", $userID);
            Yii::$app->session->set("username", $username);
            return $this->goHome();
        }

        return $this->render('signup', [
            'model' => $model,
        ]);
    }

    /**
     * 用户退出
     * @return \yii\web\Response
     */
    public function actionLogout()
    {
        Yii::$app->session->set("userid", 0);

        return $this->goHome();
    }

    /**
     * 用户登录
     * @return string|\yii\web\Response
     */
    public function actionLogin()
    {
        if (Yii::$app->session->get("userid")>0) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if (Yii::$app->request->isPost) {
            //登录, 获取邮箱, 去查id
            $LoginForm = Yii::$app->request->post("LoginForm");
            $email = $LoginForm['email'];
            $password = $LoginForm['password'];
            $userID = Yii::$app->redis->hget("email.to.id", $email);
            if(!$userID) {
                Yii::$app->session->setFlash("failure", "用户或密码错误");

                return $this->render('login', [
                    'model' => $model,
                ]);
            }

            $password = md5($password);
            $userpassword = Yii::$app->redis->hget("user:{$userID}", "password");
            if($password != $userpassword) {
                Yii::$app->session->setFlash("failure", "用户登录失败");
                return $this->render('login', [
                    'model' => $model,
                ]);
            }

            $username = Yii::$app->redis->hget("user:$userID", "username");
            Yii::$app->session->set("userid", $userID);
            Yii::$app->session->set("username", $username);
            $this->goHome();
        } else {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    /**
     * 首页, 全部微博
     * @return string
     */
    public function actionIndex()
    {

        //显示所有的用户, 分页显示

        // 如果用户没有登录则显示所有的微博
        // 用户登录过就只显示我所关注的所有的微博

        $userID = Yii::$app->session->get("userid");
        $posts = array();

        if ($userID>0) {
            $postids = Yii::$app->redis->lrange("posts:$userID", 0, -1);

            foreach ($postids as $postID) {
                $post = Yii::$app->redis->hvals("post:$postID");
                $posts[] = $post;
            }
        } else {
            for ($i=1; $i<Yii::$app->redis->get("posts:count"); $i++) {
                $post = Yii::$app->redis->hvals("post:$i");
                $posts[] = $post;
            }
        }

        $users = array();
        $usercount = Yii::$app->redis->get("users:count");
        for($i=1; $i<=$usercount; $i++) {
            $user = Yii::$app->redis->hgetall("user:$i");
            $users[] = $user;
        }

        return $this->render('index', [
            'posts' => $posts,
            'users' => $users,
        ]);
    }

    /**
     * 我的微博
     * @return string
     */
    public function actionMy()
    {
        $userID = Yii::$app->session->get("userid");
        $posts = array();

        if($userID>0) {
            $postids = Yii::$app->redis->lrange("posts:$userID", 0, -1);

            foreach ($postids as $postID) {
                $post = Yii::$app->redis->hvals("post:$postID");
                $posts[] = $post;
            }
        }

        return $this->render('my', [
            'posts' => $posts
        ]);
    }

    /**
     * 发布微博
     */
    public function actionPublish()
    {
        $model = new PublishForm();
        if (Yii::$app->request->isPost) {
            $postID = Yii::$app->redis->incr("posts:count");
            $uid = Yii::$app->session->get("userid");
            $username = Yii::$app->redis->hget("user:$uid", "username");
            $created_at = time();
            $PublishForm = Yii::$app->request->post("PublishForm");
            $content = $PublishForm['content'];
            Yii::$app->redis->hmset("post:{$postID}", "uid", $uid, "username", $username, "created_at", $created_at, "content", $content);
            Yii::$app->redis->rpush("posts:$uid", $postID);
            Yii::$app->redis->hincrby("user:$uid", "posts", 1);
            return $this->goHome();
        } else {
            return $this->render('publish', [
                'model' => $model,
            ]);
        }
    }

    /**
     * 关注动作
     *
     * ~~~
     * {
     *   'userid' <被关注的用户id>
     * }
     * ~~~
     * @return \yii\web\Response
     */
    public function actionFollow()
    {
        $userID = Yii::$app->session->get("userid");
        $followUserID = Yii::$app->request->get("userid");

        //不能关注自己
        if ($userID == $followUserID || $userID<1) {
            return $this->goHome();
        }

        // 不能重复添加粉丝
        if(!Yii::$app->redis->lindex("followers:$followUserID", $userID)) {
            Yii::$app->redis->rpush("followers:$followUserID", $userID);
            // 被关注的用户粉丝数+1
            Yii::$app->redis->hincrby("user:$followUserID", "fans", 1);
        }


        // 不能重复添加关注
        if(!Yii::$app->redis->lindex("following:$userID", $followUserID)) {
            Yii::$app->redis->rpush("following:$userID", $followUserID);
            // 当前用户的关注数+1
            Yii::$app->redis->hincrby("user:$userID", "followings", 1);
        }
        return $this->goHome();
    }

    /**
     * 我关注的人
     */
    public function actionMyFollowing()
    {
        $userID = Yii::$app->session->get("userid");
        $userids = Yii::$app->redis->lrange("following:$userID", 0, -1);

        foreach ($userids as $uid) {
            $user = Yii::$app->redis->hvals("user:$uid");
            $users[] = $user;
        }
        return $this->render('myfollowing', [
            'users' => $users,
        ]);
    }

    /**
     * 我的粉丝
     */
    public function actionMyFollowers()
    {
        $userID = Yii::$app->session->get("userid");
        $userids = Yii::$app->redis->lrange("followers:$userID", 0, -1);

        $users = array();
        foreach ($userids as $uid) {
            $user = Yii::$app->redis->hvals("user:$uid");
            $users[] = $user;
        }
        return $this->render('myfollowers', [
            'users' => $users,
        ]);
    }

    /**
     * 我的页面
     * @return string
     */
    public function actionSpace()
    {
        $userID = Yii::$app->session->get("userid");

        // 我的信息
        $userinfo = Yii::$app->redis->hvals("user:$userID");

        // 我发布的微博
        $postids = Yii::$app->redis->lrange("posts:$userID", 0, -1);
        $posts = array();
        foreach ($postids as $postID) {
            $post = Yii::$app->redis->hvals("post:$postID");
            $posts[] = $post;
        }

        // 我关注的人
        $userids = Yii::$app->redis->lrange("following:$userID", 0, -1);
        $followingusers = array();
        foreach ($userids as $uid) {
            $user = Yii::$app->redis->hvals("user:$uid");
            $followingusers[] = $user;
        }

        // 我的粉丝
        $userids = Yii::$app->redis->lrange("followers:$userID", 0, -1);
        $followersusers = array();
        foreach ($userids as $uid) {
            $user = Yii::$app->redis->hvals("user:$uid");
            $followersusers[] = $user;
        }
        return $this->render('space', [
            'userinfo' => $userinfo,
            'posts' => $posts,
            'followingusers' => $followingusers,
            'followersusers' => $followersusers,
        ]);

    }
}