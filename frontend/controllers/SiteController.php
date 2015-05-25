<?php
namespace frontend\controllers;

use Yii;
use common\models\LoginForm;
use frontend\models\PasswordResetRequestForm;
use frontend\models\ResetPasswordForm;
use frontend\models\SignupForm;
use frontend\models\ContactForm;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use frontend\models\User;

/**
 * Site controller
 */
class SiteController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout', 'signup'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionLogin()
    {
        if (!\Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        } else {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail(Yii::$app->params['adminEmail'])) {
                Yii::$app->session->setFlash('success', 'Thank you for contacting us. We will respond to you as soon as possible.');
            } else {
                Yii::$app->session->setFlash('error', 'There was an error sending email.');
            }

            return $this->refresh();
        } else {
            return $this->render('contact', [
                'model' => $model,
            ]);
        }
    }

    public function actionAbout()
    {
        return $this->render('about');
    }

    public function actionSignup()
    {
        $model = new SignupForm();
        if ($model->load(Yii::$app->request->post())) {
            if ($user = $model->signup()) {
                if (Yii::$app->getUser()->login($user)) {
                    return $this->goHome();
                }
            }
        }

        return $this->render('signup', [
            'model' => $model,
        ]);
    }

    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequestForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                Yii::$app->getSession()->setFlash('success', 'Check your email for further instructions.');

                return $this->goHome();
            } else {
                Yii::$app->getSession()->setFlash('error', 'Sorry, we are unable to reset password for email provided.');
            }
        }

        return $this->render('requestPasswordResetToken', [
            'model' => $model,
        ]);
    }

    public function actionResetPassword($token)
    {
        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidParamException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->resetPassword()) {
            Yii::$app->getSession()->setFlash('success', 'New password was saved.');

            return $this->goHome();
        }

        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }

    /**
     * test memcache
     * @return mixed|string
     */
	public function actionTestMemcache()
    {
        $key = "username";
        //Yii::$app->cache->delete($key);
        $value = Yii::$app->cache->get($key);
        if ($value === false) {
            $value = "maxwelldu ";
            Yii::$app->cache->set($key, $value, 30);
        }
        return $value;
    }

    /**
     * 测试用户注册
     */
    public function actionRedisUserRegister()
    {
        // 批量注册10个用户, todo 先检查邮箱是否唯一
        for($i=0; $i<10; $i++) {

            $userID = Yii::$app->redis->incr("users:count");
            $email = "dcj3sjt@126.com".$userID;
            Yii::$app->redis->hmset("user:{$userID}", "email", $email, "password", md5("adminadmin"), "nickname", "maxwelldu".$userID);
            Yii::$app->redis->hset("email.to.id", $email, $userID);

            echo "注册成功";
        }
    }

    /**
     * 清空所有的数据
     */
    public function actionRedisClear()
    {
        Yii::$app->redis->flushall();
        echo '已经清空所有数据';
    }

    /**
     * 用户登录
     */
    public function actionRedisUserLogin()
    {
        //登录, 获取邮箱, 去查id
        $email = "dcj3sjt@126.com1";
        $userID = Yii::$app->redis->hget("email.to.id", $email);
        if(!$userID) {
            echo '用户名或密码错误!';
            exit;
        }

        $password = md5("adminadmin");
        $userpassword = Yii::$app->redis->hget("user:{$userID}", "password");
        if($password != $userpassword) {
            echo "用户登录失败";
            exit;
        }
        echo "用户登录成功";
    }

    /**
     * 发布微博
     */
    public function actionRedisPublishPost()
    {
        // 批量发布10条微博
        for($i=0; $i<10; $i++) {
            $postID = Yii::$app->redis->incr("posts:count");
            $uid = $i+1;
            $username = "test".$i;
            $created_at = time();
            $content = "contnet ".$i;
            Yii::$app->redis->hmset("post:{$postID}", "uid", $uid, "username", $username, "created_at", $created_at, "content", $content);
            Yii::$app->redis->rpush("posts:$uid", $postID);
            echo "微博成功";
        }
    }

    /**
     * 查询userid 为 1的人的微博列表
     */
    public function actionRedisMyWeiboList()
    {
        $userID = 1;
        $posts = Yii::$app->redis->lrange("posts:$userID", 0, Yii::$app->redis->get("posts:count"));
        foreach($posts as $postID) {
            $post = Yii::$app->redis->hvals("post:$postID");
            var_dump($post);
        }
    }

    /**
     * 所有的微博
     */
    public function actionRedisWeibos()
    {
        /* todo 代码是错误的
        $posts = Yii::$app->redis->hgetall("post:*");
        foreach($posts as $post) {
            var_dump($post);
        }
        */
    }

    /**
     * 关注人
     */
    public function actionRedisFollow()
    {
        // 2, 3 关注  1
        // 1 的粉丝有 2和3, 2关注的人有1, 3关注的人有1
        $uid = 1;
        $uid2 = 2;
        $uid3 = 3;
        // todo 不能重复关注
        Yii::$app->redis->rpush("followers:$uid", $uid2); //将2添加为1的粉丝
        Yii::$app->redis->rpush("followers:$uid", $uid3); //将3添加为1的粉丝

        // todo 不能重复添加
        Yii::$app->redis->rpush("following:$uid", $uid2); //2关注的有1
        Yii::$app->redis->rpush("following:$uid", $uid3); //3关注的有1
    }

    /**
     * 我关注的人的微博
     */
    public function actionRedisFollowWeibos()
    {
        //todo
    }



    public function actionTestRedis()
    {
        //Yii::$app->redis->executeCommand('HMSET', ['user:1', 'name', 'joe', 'solary', 2000]);
//        Yii::$app->redis->set("site1", "www.baidu.com");
//        Yii::$app->redis->set("site2", "www.google.com");

//        echo Yii::$app->redis->get("site1");
//        echo Yii::$app->redis->get("site2");

//        删除 redis中的所有数据
//        Yii::$app->redis->flushall();

//        $customer = new User();
//        $customer->attributes = ['name' => 'test'];
//        $customer->save();
//        echo $customer->id; // id will automatically be incremented if not set explicitly

        /*
        $customer = User::find()->where(['name' => 'test'])->one(); // find by query
        var_dump($customer);
        $customers = User::find()->active()->all(); // find all by query
        foreach($customers as $c) {
            var_dump($c);
        }
        */
    }
}
