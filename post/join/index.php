<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>ひとこと掲示板</title>
	<link rel="stylesheet" href="../style.css" />
</head>

<body>
<div id="wrap">
  <div id="head">
    <h1>ひとこと掲示板</h1>
  </div>
  <div id="content">
    <p>次のフォームに必要事項を入力してください。</p>
    <form action="" method="post" enctype="multipart/form-data">
        <dl>
            <dt>ニックネーム<span class="required">必要</span></dt>
            <dd><input type="text" name="name" size="35" maxlength="255" /></dd>
            <dt>メールアドレス<span class="required">必要</span></dt>
            <dd><input type="text" name="email" size="35" maxlength="255" /></dd>
            <dt>パスワード<span class="required">必要</span></dt>
            <dd><input type="text" name="password" size="10" maxlength="20" /></dd>
            <dt>写真など</dt>
            <dd><input type="file" name="image" size="35" /></dd>
        </dl>
        <div><input type="submit" value="入力内容を確認する" /></div>
    </form>        
  </div>

</div>
</body>
</html>
