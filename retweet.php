<?php
session_start();
require('dbconnect.php');

        //　リツイートした場合
        if (isset($_REQUEST['retweet'])) {
            // ツイートの情報を取得
            $getTweet = $db->prepare('SELECT m.name, m.picture, p.*,count(g.good_count) as good_count FROM members m, posts p
            LEFT JOIN good g ON p.id = g.post_id WHERE m.id = p.member_id AND p.id = ? ORDER BY p.created DESC');
            $getTweet->execute(array($_REQUEST['retweet']));
            $retweet_post = $getTweet->fetch();
            
            // 取得したツイート情報のretweet_post_idから元ツイートを取得
            $getTweet_rel = $db->prepare('SELECT m.name, m.picture, p.*,count(g.good_count) as good_count FROM members m, posts p
            LEFT JOIN good g ON p.id = g.post_id WHERE m.id = p.member_id AND p.id = ? ORDER BY p.created DESC');
            $getTweet_rel->execute(array($retweet_post['retweet_post_id']));
            $retweet_post_rel = $getTweet_rel->fetch();

            //　ログインユーザーのIDとリツイートした投稿のIDを検索
            $usersFindret = $db->prepare('SELECT COUNT(*) AS ret_cnt FROM posts WHERE retweet_post_id = ? AND retweet_member_id = ?');
            $usersFindret->execute(array($_REQUEST['retweet'],$_SESSION['id']));
            $retweet_check = $usersFindret->fetch();

            // ログインユーザーがリツイート済の投稿ではないかチェック    
            $findRet = $db->prepare('SELECT COUNT(*) AS ret_cnt FROM posts WHERE retweet_post_id = ? AND retweet_member_id = ?');
            $findRet->execute(array($retweet_post['retweet_post_id'], $_SESSION['id']));
            $findRetCheck = $findRet->fetch();
            
            // 登録処理（リツイートしていない場合）
            if($retweet_check['ret_cnt'] < 1 && $findRetCheck['ret_cnt'] < 1) {
                if($retweet_post['retweet_post_id'] < 1) { 
                    $retweet_record = $db->prepare('INSERT INTO posts SET message = ?, member_id = ?,retweet_post_id = ?,retweet_member_id = ?, created = NOW()');
                    $retweet_record->execute(array($retweet_post['message'],$retweet_post['member_id'],$_REQUEST['retweet'],$_SESSION['id']));
                    // 登録したリツイート情報を取得
                    $get_retweet = $db->prepare('SELECT * FROM posts WHERE retweet_post_id = ? AND retweet_member_id = ?');
                    $get_retweet->execute(array( $_REQUEST['retweet'],$_SESSION['id']));
                    $get_retweets = $get_retweet->fetch();
                    // 各投稿のリツイート総数を取得
                    $ret_getcount = $db->prepare('SELECT *,COUNT(*) as get_cnt from posts where retweet_post_id = ?');
                    $ret_getcount->execute(array($_REQUEST['retweet']));
                    $ret_getcounts = $ret_getcount->fetch();
                    // goodテーブルに登録
                    $good_take = $db->prepare('INSERT INTO good SET post_id = ?,retweet_post_id = ?,member_id = ?, good_count = ?, created = NOW()');
                    $good_take->execute(array($get_retweets['id'],$_REQUEST['retweet'],$_SESSION['id'],$retweet_post['good_count']));
                    //  取得したリツイート総数でリツイート元のリツイート数を更新
                    $update_ret = $db->prepare('UPDATE posts set retweet_counts = ? where id = ?');
                    $update_ret->execute(array($ret_getcounts['get_cnt'],$_REQUEST['retweet']));
                    // 更新されたリツイート総数を同じくリツイートされている投稿に反映させる
                    $same_update_ret = $db->prepare('UPDATE posts SET retweet_counts = ? where retweet_post_id = ?');
                    $same_update_ret->execute(array($ret_getcounts['get_cnt'],$_REQUEST['retweet']));
                //リツイートしていた場合の登録処理
                } else {
                    $retweet_record = $db->prepare('INSERT INTO posts SET message = ?, member_id = ?,retweet_post_id = ?,retweet_member_id = ?, created = NOW()');
                    $retweet_record->execute(array($retweet_post['message'],$retweet_post['member_id'],$retweet_post['retweet_post_id'],$_SESSION['id']));
                    // 登録したリツイート情報を取得
                    $get_retweet = $db->prepare('SELECT * FROM posts WHERE retweet_post_id = ? AND retweet_member_id = ?');
                    $get_retweet->execute(array($retweet_post['retweet_post_id'],$_SESSION['id']));
                    $get_retweets = $get_retweet->fetch();
                    // 各投稿のリツイート総数を取得
                    $ret_getcount = $db->prepare('SELECT *,COUNT(*) as get_cnt from posts WHERE retweet_post_id = ?');
                    $ret_getcount->execute(array($retweet_post['retweet_post_id']));
                    $ret_getcounts = $ret_getcount->fetch();
                    // goodテーブルに登録
                    $good_take = $db->prepare('INSERT INTO good SET post_id = ?,retweet_post_id = ?,member_id = ?, good_count = ?, created = NOW()');
                    $good_take->execute(array($get_retweets['id'],$retweet_post['retweet_post_id'],$_SESSION['id'],$retweet_post_rel['good_count']));
                    //  取得したリツイート総数でリツイート元のリツイート数を更新
                    $update_ret = $db->prepare('UPDATE posts SET retweet_counts = ? WHERE id = ?');
                    $update_ret->execute(array($ret_getcounts['get_cnt'],$retweet_post['retweet_post_id']));
                    // 更新されたリツイート総数を同じくリツイートされている投稿に反映させる
                    $same_update_ret = $db->prepare('UPDATE posts SET retweet_counts = ? WHERE retweet_post_id = ?');
                    $same_update_ret->execute(array($ret_getcounts['get_cnt'],$retweet_post['retweet_post_id']));
                }
                        header('Location: index.php'); exit();
                // 削除処理        
                } else {
                    if($retweet_post['retweet_member_id'] === $_SESSION['id']) {
                    // リツイートした投稿を削除
                    $retdelete = $db->prepare('DELETE FROM posts WHERE id = ? AND retweet_member_id = ?');
                    $retdelete->execute(array($_REQUEST['retweet'],$_SESSION['id'] ));
                    // 削除後の件数取得
                    $delcount = $db->prepare('SELECT *,COUNT(*) as get_cnt from posts where retweet_post_id = ?');
                    $delcount->execute(array($retweet_post_rel['id']));
                    $delcounts = $delcount->fetch();

                    $same_ret = $db->prepare('UPDATE posts SET retweet_counts = ? where id = ?');
                    $same_ret->execute(array(
                        $delcounts['get_cnt'],
                        $retweet_post_rel['id']
                    )); 

                    $del_same_ret = $db->prepare('UPDATE posts SET retweet_counts = ? where retweet_post_id = ?');
                    $del_same_ret->execute(array(
                        $delcounts['get_cnt'],
                        $retweet_post_rel['id']
                    )); 

                    $del_same = $db->prepare('UPDATE posts SET retweet_counts = ? where id = ?');
                    $del_same->execute(array(
                        $delcounts['get_cnt'],
                        $_REQUEST['retweet']
                    ));
                    } else if($retweet_post['retweet_post_id'] < 1){
                         // リツイートした投稿を更にリツイートしようとした場合は取り消し
                        $retweet_delete = $db->prepare('DELETE FROM posts WHERE retweet_post_id = ? AND retweet_member_id = ?');
                        $retweet_delete->execute(array(
                            $_REQUEST['retweet'],
                            $_SESSION['id']
                        ));
                        // 削除後の件数取得
                        $delcount_org = $db->prepare('SELECT *,COUNT(*) as get_cnt from posts where retweet_post_id = ?');
                        $delcount_org->execute(array($retweet_post['id']));
                        $delcounts_org = $delcount_org->fetch();

                        $del_same_org = $db->prepare('UPDATE posts SET retweet_counts = ? where id = ?');
                        $del_same_org->execute(array(
                            $delcounts_org['get_cnt'],
                            $_REQUEST['retweet']
                        ));

                        $same_org = $db->prepare('UPDATE posts SET retweet_counts = ? where retweet_post_id = ?');
                        $same_org->execute(array(
                            $delcounts_org['get_cnt'],
                            $_REQUEST['retweet']
                        ));
                    } else {
                        $retweet_delete_rel = $db->prepare('DELETE FROM posts WHERE retweet_post_id = ? AND retweet_member_id = ?');
                        $retweet_delete_rel->execute(array(
                            $retweet_post['retweet_post_id'],
                            $_SESSION['id']
                        ));
                        // 削除後の件数取得
                        $delcount_org = $db->prepare('SELECT *,COUNT(*) as get_cnt from posts where retweet_post_id = ?');
                        $delcount_org->execute(array($retweet_post['retweet_post_id']));
                        $delcounts_org = $delcount_org->fetch();

                        $del_same_org = $db->prepare('UPDATE posts SET retweet_counts = ? where id = ?');
                        $del_same_org->execute(array(
                            $delcounts_org['get_cnt'],
                            $retweet_post['retweet_post_id']
                        ));

                        $same_org = $db->prepare('UPDATE posts SET retweet_counts = ? where retweet_post_id = ?');
                        $same_org->execute(array(
                            $delcounts_org['get_cnt'],
                            $retweet_post['retweet_post_id']
                        ));
                    }

                    //  いいねテーブルの上記リツイート情報も削除
                    $retweet_good_del = $db->prepare('DELETE FROM good WHERE retweet_post_id = ? AND member_id = ?');
                    $retweet_good_del->execute(array(
                        $_REQUEST['retweet'],
                        $_SESSION['id']
                        ));
                        $redirect_url = "index.php?page=" . $page;

                        header("Location:" . $redirect_url); exit();
            }
        }
                    //　リツイートしたユーザー名を表示するため、idとnameをペアにして配列として取得する
                     $retweet_username = $db->query('SELECT id, name FROM members');
                     $retweet_username = $retweet_username->fetchAll(PDO::FETCH_KEY_PAIR);