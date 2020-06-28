<?php
require('../dbconnect.php');

session_start();

//フォーム送信されたかどうかの確認処理
if (!empty($_POST)) {
    //エラー項目の確認（会員登録フォームに入力された内容が空かどうか判定）
    if ($_POST['name'] === '') {
        $error['name'] = 'blank';
    }
    if ($_POST['email'] === '') {
        $error['email'] = 'blank';
    }    
    if(!preg_match("/^([a-zA-Z0-9])+([a-zA-Z0-9\?\*\[|\]%'=~^\{\}\/\+!#&\$\._-])*@([a-zA-Z0-9_-])+\.([a-zA-Z0-9\._-]+)+$/",$_POST['email'])){
        $error['email'] = 'error';
    }
  
    //パスワードが4文字以下の時にエラー処理
    if (strlen($_POST['password']) < 4) {
        $error['password'] = 'length';
    }
    if ($_POST['password'] === '') {
        $error['password'] = 'blank';
    }
    //画像ファイルチェック
    $fileName = $_FILES['image']['name'];
    if (!empty($fileName)) {
        $ext = substr($fileName, -3);
        if ($ext != 'jpg' && $ext != 'gif') {
            $error['image'] = 'type';
        }
    }

    //　重複アカウントのチェック
    if (empty($error)) {
        $member = $db->prepare('SELECT COUNT(*) AS cnt FROM members WHERE email=?');
        $member->execute(array($_POST['email']));
        $record = $member->fetch();
        if ($record['cnt'] > 0) {
            $error['email'] = 'duplicate';
        }
    }
    
    if (empty($error)) {
        //画像アップロード
        $image = date('YmdHis') . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], '../member_picture/' .$image);

        $_SESSION['join'] = $_POST;
        $_SESSION['join']['image'] = $image;
        header('Location: check.php');
        exit();
    }   
}

//　書き直し
if ($_REQUEST['action'] == 'rewrite') {
    $_POST = $_SESSION['join'];
    $error['rewrite'] = true; //画像指定エラーを表示させるための処理
}
?>



<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>ひとこと掲示板</title>
	<link rel="stylesheet" href="../css/style.css" />
</head>

<body>
<div id="wrap">
  <div id="head">
    <h1>会員登録</h1>
  </div>
  <div id="content">
    <p>次のフォームに必要事項を入力してください。</p>
    <form action="" method="post" enctype="multipart/form-data">
        <dl>
            <dt>ニックネーム<span class="required">必須</span></dt>
            <dd>
                <input type="text" name="name" size="35" maxlength="255" value="<?php echo htmlspecialchars($_POST['name'], ENT_QUOTES); ?>" />
                <?php if($error['name'] === 'blank'): ?>
                <p class="error"> *　ニックネームを入力してください</p>
                <?php endif; ?>
            </dd>
            <dt>メールアドレス<span class="required">必須</span></dt>
            <dd>
                <input type="text" name="email" size="35" maxlength="255" value="<?php echo htmlspecialchars($_POST['email'], ENT_QUOTES); ?>" />
                <?php if($error['email'] === 'blank'): ?>
                <p class="error"> *　メールアドレスを入力してください</p>
                <?php endif; ?>
                <?php if ($error['email'] == 'duplicate'): ?>
                <p class="error"> *　指定されたメールアドレスは既に登録されています。</p>
                <?php endif; ?>
                <?php if ($error['email'] == 'error'): ?>
                <p class="error"> *　正式なメールアドレスを入力してください。</p>
                <?php endif; ?>
            </dd>
            <dt>パスワード<span class="required">必須</span></dt>
            <dd>
                <input type="text" name="password" size="10" maxlength="20" value="<?php echo htmlspecialchars($_POST['password'],ENT_QUOTES); ?>" />
                <?php if($error['password'] === 'blank'): ?>
                <p class="error"> *　パスワードを入力してください</p>
                <?php endif; ?>
                <?php if ($error['password'] === 'length'): ?>
                <p class="error"> *　パスワードは4文字以上です。</p>   
                <?php endif; ?>
            </dd>
            <dt>写真など</dt>
            <dd>
                <input type="file" name="image" size="35" value="test" />
                <?php if ($error['image'] == 'type'): ?>
                <p class="error"> *　写真などは「.gif」「.jpg」の画像を指定してください</p>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                <p class="error"> *　恐れ入りますが、画像を再指定してください。</p>
                <?php endif; ?>
            </dd>
        </dl>
        <div><input type="submit" value="入力内容を確認する" /></div>
    </form>        
  </div>

</div>
</body>
</html>
