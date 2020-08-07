<?php
session_start();
require('dbconnect.php');

// いいね機能のバリデーションチェック
if(isset($_REQUEST['good'])) {
    if(!preg_match('/^0$|^[1-9][0-9]*$/',$_REQUEST['good'])) {
        echo '不正な入力です';
        exit();
    }
} else {
    echo '不正な入力です';
}

try {
    //エラー、例外処理を投げる
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //トランザクション開始
    $db->beginTransaction();

    //　いいね！実行
    if ($_REQUEST['good']) {
        // いいねした投稿情報を取得
        $tweet_info = $db->prepare('SELECT * FROM posts WHERE id = ?');
        $tweet_info->execute(array($_REQUEST['good']));
        $tweet_main_info = $tweet_info->fetch();

        // いいねした投稿IDとログインユーザーIDからいいね済かチェック
        $users_find_good = $db->prepare('SELECT * FROM good WHERE post_id = ? AND member_id = ?');
        $users_find_good->execute(array( $_REQUEST['good'], $_SESSION['id']));
        $good_check = $users_find_good->fetch();
        
        // いいね済のリツイートであるかチェック
        $users_ret_check = $db->prepare('SELECT * FROM good WHERE retweet_post_id = ? AND member_id = ?');
        $users_ret_check->execute(array( $tweet_main_info['retweet_post_id'], $_SESSION['id']));
        $ret_good_check = $users_ret_check->fetch();

        $check = $db->prepare('SELECT * FROM good WHERE post_id = ? AND member_id = ?');
        $check->execute(array( $tweet_main_info['retweet_post_id'], $_SESSION['id']));
        $ret_check = $check->fetch();

        // リツイートをいいね！
        if($tweet_main_info['retweet_post_id'] > 0) {
            //　リツイート済かチェック
            if(isset($ret_check['good_count'])) {
                // 削除及びいいね数を更新処理
                $good_delete = $db->prepare('DELETE FROM good WHERE post_id = ? AND member_id = ?');
                $good_delete->execute(array( $tweet_main_info['retweet_post_id'], $_SESSION['id']));
                // いいね総数を取得
                $del_good_cnt = $db->prepare('SELECT count(*) as good_count FROM posts as p JOIN good as g ON p.id = g.post_id WHERE g.post_id = ? GROUP BY p.id');
                $del_good_cnt->execute(array($tweet_main_info['retweet_post_id']));
                $del_good_count = $del_good_cnt->fetch(); 

                $good_del = $db->prepare('UPDATE good SET good_count = ? WHERE post_id = ? ');
                $good_del->execute(array( $del_good_count['good_count'], $tweet_main_info['retweet_post_id'],));

                $ret_good_del = $db->prepare('UPDATE good SET good_count = ? WHERE retweet_post_id = ?');
                $ret_good_del->execute(array( $del_good_count['good_count'], $tweet_main_info['retweet_post_id']));
            } else {
                // いいね登録及びいいね数更新処理
                $good_record = $db->prepare('INSERT INTO good SET post_id = ?, member_id = ?, created = NOW()');
                $good_record->execute(array( $tweet_main_info['retweet_post_id'], $_SESSION['id']));
                // いいね総数を取得
                $get_good_cnt = $db->prepare('SELECT count(*) as good_count FROM posts as p JOIN good as g ON p.id = g.post_id WHERE g.post_id = ? GROUP BY p.id');
                $get_good_cnt->execute(array($tweet_main_info['retweet_post_id']));
                $good_count = $get_good_cnt->fetch();

                $update_good_cnt = $db->prepare('UPDATE good SET good_count = ? WHERE post_id = ?');
                $update_good_cnt->execute(array( $good_count['good_count'], $tweet_main_info['retweet_post_id']));

                $update_ret_good = $db->prepare('UPDATE good SET good_count = ? WHERE retweet_post_id = ?');
                $update_ret_good->execute(array( $good_count['good_count'], $tweet_main_info['retweet_post_id']));
            }
         // トランザクション完了（コミット）
        $db->commit();

        header('Location: index.php');
        exit();    
        // ツイートをいいね！
        } else {
            if($good_check['good_count'] > 0) {
                $good_delete = $db->prepare('DELETE FROM good WHERE post_id = ? AND member_id = ?');
                $good_delete->execute(array( $_REQUEST['good'], $_SESSION['id']));

                $del_good_cnt = $db->prepare('SELECT count(*) as good_count FROM posts as p JOIN good as g ON p.id = g.post_id WHERE g.post_id = ? GROUP BY p.id');
                $del_good_cnt->execute(array($_REQUEST['good']));
                $del_good_count = $del_good_cnt->fetch(); 

                $good_del = $db->prepare('UPDATE good SET good_count = ? WHERE post_id = ? ');
                $good_del->execute(array( $del_good_count['good_count'], $_REQUEST['good']));

                $ret_good_del = $db->prepare('UPDATE good SET good_count = ? WHERE retweet_post_id = ?');
                $ret_good_del->execute(array( $del_good_count['good_count'], $_REQUEST['good']));
            } else {
                $good_record = $db->prepare('INSERT INTO good SET post_id = ?, member_id = ?, created = NOW()');
                $good_record->execute(array( $_REQUEST['good'], $_SESSION['id']));

                $get_good_cnt = $db->prepare('SELECT count(*) as good_count FROM posts as p JOIN good as g ON p.id = g.post_id WHERE g.post_id = ? GROUP BY p.id');
                $get_good_cnt->execute(array($_REQUEST['good']));
                $good_count = $get_good_cnt->fetch();

                $update_good_cnt = $db->prepare('UPDATE good SET good_count = ? WHERE post_id = ?');
                $update_good_cnt->execute(array( $good_count['good_count'], $tweet_main_info['id']));
                
                $update_ret_good = $db->prepare('UPDATE good SET good_count = ? WHERE retweet_post_id = ?');
                $update_ret_good->execute(array( $good_count['good_count'], $tweet_main_info['id']));
            }
        // トランザクション完了（コミット）
        $db->commit();

        header('Location: index.php'); 
        exit();

        }
    }

} catch (PDOException $e) {
    // ロールバックでトランザクションを取り消し
    $db->rollback();
    echo '登録エラー : ' . $e->getMessage();
    exit();
}
