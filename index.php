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

// いいね機能実装--------------------------------------------------------------------------------------
//　いいね！されたとき
    if (isset($_REQUEST['good'])) {
        // いいね済みの投稿ではないかチェック
        $good = $db->prepare('SELECT COUNT(*) AS cnt FROM good WHERE post_id = ? AND member_id = ?');
        $good->execute(array(
            $_REQUEST['good'],
            $_SESSION['id']
        ));
        // 抽出された件数を代入
        $good_comment = $good->fetch();

        // 抽出された投稿が新規の場合の処理と登録済の場合の処理
        if ($good_comment['cnt'] < 1) {
            // 新規の場合（いいね保存）
            $good_record = $db->prepare('INSERT INTO good SET post_id = ?, member_id = ?, created = NOW()');
            $good_record->execute(array(
                $_REQUEST['good'],
                $_SESSION['id']
            ));
            // いいねを実行したページ数を渡してトップに戻らないようにする
            $redirect_url = "index.php?page=" . $page;
            header("Location:" . $redirect_url); exit();
        } else {
            // いいね済の場合（いいね削除）
            $good_del = $db->prepare('DELETE FROM good WHERE post_id = ? AND member_id = ?');
            $good_del->execute(array(
                $_REQUEST['good'],
                $_SESSION['id']
            ));
            $redirect_url = "index.php?page=" . $page;
            header("Location:" . $redirect_url); exit();
        }  

    }
// いいね機能ここまで----------------------------------------------------------------------------------

//　リツイート機能-------------------------------------------------------------------------------------
    //　リツイートした場合
    if (isset($_REQUEST['retweet'])) {
        // リツイートする元ツイートを取得
        $getTweet = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id = p.member_id AND p.id = ? ORDER BY p.created DESC');
        $getTweet->execute(array($_REQUEST['retweet']));
            $retweet_post = $getTweet->fetch();
                      
        //　ログインユーザーがリツイート済みかチェック
        $usersRetweet = $db->prepare('SELECT COUNT(*) AS ret_cnt FROM posts WHERE retweet_member_id = ? AND retweet_post_id = ?');
        $usersRetweet->execute(array(
            $_SESSION['id'],
            $_REQUEST['retweet']
            ));
            $retweet_cnt = $usersRetweet->fetch();

            // ユーザーがリツイートしていなかった場合は＋１
            if($retweet_cnt['ret_cnt'] < 1) {
                $retweet_record = $db->prepare('INSERT INTO posts SET message = ?, member_id = ?,retweet_post_id = ?,retweet_member_id = ?, created = NOW()');
                $retweet_record->execute(array(
                    $retweet_post['message'],
                    $retweet_post['member_id'],
                    $_REQUEST['retweet'],
                    $_SESSION['id']
                    ));
                //　各投稿のリツイート総数を取得
                $ret_getcount = $db->prepare('SELECT COUNT(*) as get_cnt from posts where retweet_post_id = ?');
                $ret_getcount->execute(array(
                    $_REQUEST['retweet']
                ));
                $ret_getcounts = $ret_getcount->fetch();
                
                //  上記で取得したリツイート総数でリツイート元のカウントを更新
                $update_ret = $db->prepare('UPDATE posts set retweet_counts = ? where id = ?');
                $update_ret->execute(array(
                    $ret_getcounts['get_cnt'],
                    $_REQUEST['retweet']));

                //  更新されたリツイート総数を同じくリツイートされている投稿に反映させる
                $same_ret_update = $db->prepare('UPDATE posts SET retweet_counts = ? where retweet_post_id = ?');
                $same_ret_update->execute(array(
                    $ret_getcounts['get_cnt'],
                    $_REQUEST['retweet']));

                    // リツイートしたらトップへ遷移
                    header('Location: index.php'); exit();
            } else {
                // リツイートしていた場合は削除
                $retweet_del = $db->prepare('DELETE FROM posts WHERE retweet_post_id = ? AND retweet_member_id = ?');
                $retweet_del->execute(array(
                    $_REQUEST['retweet'],
                    $_SESSION['id']
                    ));
                // リツイートを取り消しされた場合にリツイート数も合わせて更新する
                $del_ret_count = $db->prepare('SELECT COUNT(*) as get_cnt from posts where retweet_post_id = ?');
                $del_ret_count->execute(array(
                $_REQUEST['retweet']
                ));
                $del_ret_counts = $del_ret_count->fetch();
                //  更新されたリツイート総数を同じくリツイートされている投稿に反映させる
                $del_same_ret = $db->prepare('UPDATE posts SET retweet_counts = ? where id = ?');
                $del_same_ret->execute(array(
                    $ret_getcounts['get_cnt'],
                    $_REQUEST['retweet']));

                    // リツイートした投稿のページへ
                    $redirect_url = "index.php?page=" . $page;
                    header("Location:" . $redirect_url); exit();
            }
        }
                    //　リツイートしたユーザー名を表示するため、idとnameをペアにして配列として取得する
                     $retweet_username = $db->query('SELECT id, name FROM members');
                     $retweet_username = $retweet_username->fetchAll(PDO::FETCH_KEY_PAIR);
                    // var_dump($rt_id_name);
//　リツイート機能ここまで-------------------------------------------------------------------------------------

?>

<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>ひとこと掲示板</title>

        <link rel="stylesheet" href="./css/style.css" />
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
                        <?php if ($post['retweet_post_id'] > 0): ?><p><?php echo h($retweet_username[$post['retweet_member_id']]); ?>さんがリツイート</p><?php endif; ?>
                        <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
                        <?php echo makeLink(h($post['message']));?><span class="name">（<?php echo h($post['name']); ?>)</span>
                        [<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>
                        <p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a></p>
                    <!-- 返信の場合 -->
                    <?php if ($post['reply_post_id'] > 0): ?>
                        <p><a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">返信元のメッセージ</a></p>
                    <?php endif; ?>
                    <?php if ($_SESSION['id'] == $post['member_id']): ?>
                        <p>[<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color:#F33;">削除</a>]</p>
                    <?php endif; ?>
                
                    <!-- 表示するリツイート総数を取得 -->
                    <?php
                    $ret_count = $db->prepare('SELECT retweet_counts FROM posts where id = ?');
                    $ret_count->execute(array($post['id']));
                    $get_Retweet = $ret_count->fetch();
                    ?>

                    <!-- リツイートボタン実装　リツイートしていた場合 -->
                    <?php if ($retweet_cnt ['ret_cnt'] > 0): ?>
                        <div class="retweet_btn">
                        <p><a href="index.php?retweet=<?php echo h($post['id']); ?>
                        &page=<?php echo h($page); ?>"style="color:green;">リツイート</a><?php echo h($get_Retweet['retweet_counts']); ?></p>
                        </div>
                    <?php else : ?>
                        <div class="retweet_btn">
                        <p><a href="index.php?retweet=<?php echo h($post['id']); ?>
                        &page=<?php echo h($page); ?>">リツイート</a><?php echo h($get_Retweet['retweet_counts']); ?></p>
                        </div>
                    <?php endif; ?>
            
                    <!-- 各投稿コメントにログインユーザーがいいねをしているかチェックする -->
                    <?php
                    $usersGood = $db->prepare('SELECT COUNT(*) AS good_cnt_check FROM good WHERE post_id = ? AND member_id = ?');
                    $usersGood->execute(array(
                        $post['id'],
                        $_SESSION['id']
                    ));
                    $usersGoodCnt = $usersGood->fetch();
                    ?>
                    <!-- いいねボタン実装　ログインユーザーがいいねをしていた場合 -->
                    <?php if ($usersGoodCnt['good_cnt_check'] > 0): ?>
                        <div class="good_btn">
                            <!-- 投稿コメントのIDをリクエストパラメータへ & いいね数表示 -->
                            <p><a href="index.php?good=<?php echo h($post['id']); ?>
                            &page=<?php echo h($page); ?>"style="color:pink;">いいね!</a><?php echo h($post['good_cnt']); ?></p>
                        </div>
                    <!-- いいねをしていない場合 -->
                    <?php else : ?>
                        <div class="good_btn">
                            <p><a href="index.php?good=<?php echo h($post['id']); ?>
                            &page=<?php echo h($page); ?>">いいね!</a><?php echo h($post['good_cnt']); ?></p>
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
