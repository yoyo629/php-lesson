<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
	// ログインチェック
	$_SESSION['time'] = time();
	$members = $db->prepare('SELECT * FROM members WHERE id=?');
	$members->execute(array($_SESSION['id']));
	$member = $members->fetch();
} else {
	// ログインしていない場合はログインページへ
	header('Location: login.php'); exit();
}
// 投稿を記録する
if (!empty($_POST)) {
	if ($_POST['message'] != '') {
		$message = $db->prepare('INSERT INTO posts SET member_id=?, message=?,reply_post_id=?,created=NOW()');
		$message->execute(array(
            $member['id'],
			$_POST['message'],
            $_POST['reply_post_id']
        ));
		header('Location: index.php'); exit();
	}
}
        // 投稿を取得する
        $page = $_REQUEST['page'];
            if ($page == '') {
                $page = 1;
            }
                $page = max($page, 1);

        // 最終ページを取得する
        $counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
        $cnt = $counts->fetch();
        $maxPage = ceil($cnt['cnt'] / 5);
        $page = min($page, $maxPage);

        $start = ($page - 1) * 5;
        // 各ページ毎に投稿を5件取得する
        $posts = $db->prepare('SELECT m.name,m.picture,p.*,count(g.post_id) AS good_cnt FROM members m, posts p 
        LEFT JOIN good g ON p.id = g.post_id WHERE m.id = p.member_id GROUP BY p.id ORDER BY p.created DESC LIMIT ?,5');
        $posts->bindParam(1, $start, PDO::PARAM_INT);
        $posts->execute();

    // 返信の場合
    if (isset($_REQUEST['res'])) {
        $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m,	posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
            $response->execute(array($_REQUEST['res']));
            $table = $response->fetch();
            $message = '@' . $table['name'] . ' ' . $table['message'];
        }
        // htmlspecialcharsのショートカット
        function h($value) {
            return htmlspecialchars($value, ENT_QUOTES);
        }
        // 本文内のURLにリンクを設定します
        function makeLink($value) {
            return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)",'<a href="\1\2">\1\2</a>' , $value);
        }

    //　リツイートしたユーザー名を表示するため、idとnameをペアにして配列として取得する
    $retweet_username = $db->query('SELECT id, name FROM members');
    $retweet_username = $retweet_username->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>ひとこと掲示板</title>
        <link rel="stylesheet" href="css/style.css" />
        </head>
        <body>
            <div id="wrap">
                <div id="head">
                    <h1>ひとこと掲示板</h1>
                </div>
                <div id="content">
                        <div style="text-align: right"><a href="logout.php">ログアウト</a></div>
                        <form action="" method="post">
                            <dl>
                                <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
                            <dd>
                            <textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
                            <input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
                            </dd>
                            </dl>
                            <div>
                            <input type="submit" value="投稿する" />
                            </div>
                        </form>

                        <?php foreach ($posts as $post): ?>
                            <div class="msg">
                                <!-- リツイートされたメッセージを表示 -->
                            <?php if ($post['retweet_post_id'] > 0): ?>
                              <p><?php echo h($retweet_username[$post['retweet_member_id']]); ?>さんがリツイート</p>
                            <?php endif; ?>
                            <?php if(preg_match('/\.png$|\.jpg$/i',$post['picture'])): ?>
                                <img src="member_picture/<?php echo h($post['picture']); ?>" width="40" height="40" alt="<?php echo h($post['name']); ?>" />
                            <?php else: ?>
                                <p>【Noimage】</p>
                            <?php endif; ?>
                                <?php echo makeLink(h($post['message']));?><span class="name">（<?php echo h($post['name']); ?>)</span>
                                [<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>
                                <p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a></p>
                            <!-- 返信の場合 -->
                            <?php if ($post['reply_post_id'] > 0): ?>
                                <div class='reply'>
                                <p><a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">返信元のメッセージ</a></p>
                                </div>
                            <?php endif; ?>
                            <?php if ($_SESSION['id'] == $post['member_id']): ?>
                                <p>[<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color:#F33;">削除</a>]</p>
                            <?php endif; ?>
                                <!-- 表示するリツイート総数を取得 -->
                                <?php
                                // リツイート総数
                                $ret_count = $db->prepare('SELECT retweet_counts FROM posts where id = ?');
                                $ret_count->execute(array($post['id']));
                                $get_Retweet = $ret_count->fetch();
                                // リツイートボタンの表示状態変更に伴うチェック
                                $ret_user = $db->prepare('SELECT * FROM posts WHERE retweet_post_id = ? AND retweet_member_id = ?');
                                $ret_user->execute(array($post['id'],$_SESSION['id']));
                                $ret_btn = $ret_user->fetch();

                                $retweet_user = $db->prepare('SELECT * FROM posts WHERE retweet_post_id = ? AND retweet_member_id = ?');
                                $retweet_user->execute(array($post['retweet_post_id'],$_SESSION['id']));
                                $retweet_btn = $retweet_user->fetch();
                                ?>
                            <!-- リツイートボタン実装　リツイートしていた場合 -->
                            <?php if (isset($ret_btn['retweet_counts']) || isset($retweet_btn['retweet_counts'])): ?>
                              <div class="retweet_btn">
                                 <a href="retweet.php?retweet=<?php echo h($post['id']); ?>&page=<?php echo h($page); ?>"style="color:green;">リツイート</a><?php echo h($get_Retweet['retweet_counts']); ?>
                              </div>
                            <?php else : ?>
                              <div class="retweet_btn">
                                 <a href="retweet.php?retweet=<?php echo h($post['id']); ?>&page=<?php echo h($page); ?>">リツイート</a><?php echo h($get_Retweet['retweet_counts']); ?>
                              </div>
                            <?php endif; ?>
                                <?php
                                // いいね総数
                                $goodCount = $db->prepare('SELECT p.*,ifnull(g.good_count,0) as good_count FROM posts as p LEFT JOIN good as g ON p.id = g.post_id WHERE p.id = ? GROUP BY g.post_id');
                                $goodCount->execute(array($post['id']));
                                $all_Goodcnt = $goodCount->fetch();
                                // いいねボタンの表示状態変更に伴うチェック
                                $good_user = $db->prepare('SELECT * FROM good WHERE post_id = ? AND member_id = ?');
                                $good_user->execute(array($post['id'],$_SESSION['id']));
                                $good_btn = $good_user->fetch();

                                $ret_good_user = $db->prepare('SELECT * FROM good WHERE post_id = ? AND member_id = ?');
                                $ret_good_user->execute(array($post['retweet_post_id'],$_SESSION['id']));
                                $ret_good_btn = $ret_good_user->fetch();
                                ?>
                            <!-- いいねボタン実装　ログインユーザーがいいねをしていた場合 -->
                            <?php if (isset($good_btn['good_count']) && $good_btn['retweet_post_id'] < 1 || isset($ret_good_btn['good_count'])): ?>
                              <div class="good_btn">
                                <a href="good.php?good=<?php echo h($post['id']); ?>&page=<?php echo h($page); ?>"style="color:pink;">いいね!</a><?php echo h($all_Goodcnt['good_count']); ?>
                              </div>
                            <!-- いいねをしていない場合 -->
                            <?php else : ?>
                              <div class="good_btn">
                                <a href="good.php?good=<?php echo h($post['id']); ?>&page=<?php echo h($page); ?>">いいね!</a><?php echo h($all_Goodcnt['good_count']); ?>
                              </div>
                            <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <ul class="paging">
                        <?php
                        if ($page > 1) {
                        ?>
                        <li><a href="index.php?page=<?php print($page - 1); ?>">前のページ</a></li>
                        <?php
                        } else {
                        ?>
                        <li>前のページへ</li>
                        <?php
                        }
                        ?>
                        <?php
                        if ($page < $maxPage) {
                        ?>
                        <li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
                        <?php    
                        } else {
                        ?>
                        <li>次のページへ</li>
                        <?php
                        }
                        ?>
                        </ul>
                </div>
            </div>
        </body>
</html>
