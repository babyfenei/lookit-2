#!/bin/bash
USERNAME="cactiuser"           #数据库用户名
PASSWORD="cactiuser"    #数据库密码
DBNAME="cacti"            #Cacti使用的数据库名称
TABLE="settings"
MYSQL_CMD="mysql  -u${USERNAME} -p${PASSWORD}"

rm -rf /tmp/wechat/      #删除旧的下载列表文件
mkdir -p /tmp/wechat/
#select replace(title_cache,'*','') 此语句是去除图形标题中的*号 我的所有图形树中的图形都有*号 如果没
有可将本语句改为 select title_cache,
select_db_sql=" select value from settings where name = 'thold_wechat_cropid' OR name = 'thold_wechat_secret' OR name = 'thold_wechat_appid';"
echo ${select_db_sql}  | ${MYSQL_CMD}  ${DBNAME}    > /tmp/wechat/list.log              #查询图形树表中的图形ID非0的数据并将结果保存至下载列表
CropID=$(cat /tmp/wechat/list.log | head -n 3 | tail -n 1)
Secret=$(cat /tmp/wechat/list.log | head -n 4 | tail -n 1)
WechatID=$(cat /tmp/wechat/list.log | head -n 2 | tail -n 1)
Users=$1
#用户名
wx_msg=$2
#message变量传入的信息
wx_sub=$3
#subject变量传入的信息
wx_msg=$(echo "$wx_msg"|sed 's/<br>/\n/g'|sed 's/<html>\|<\/html>\|<body>\|<\/body>\|<strong>\|<\/strong>\|<GRAPH>\|<a\|<\/a>//g'|sed 's/>/\n/g')
wx_msg=$(echo "$wx_msg"|sed 's/\/\/graph.php/\/graph.php/g')


#############################################以下为相关变量处理##################################################
UserID=${Users//,/|}
#替换微信账号分割方式为|
#替换message中的html标签
wx_msg=$(echo "$wx_msg"|sed 's/\/\/graph.php/\/graph.php/g')
#替换网址中的//为/
Date=$(date '+%Y/%m/%d %H:%M:%S\n\n')
#应cactifans群内要求，添加Cacti微信报警日期参数
Tit=$wx_sub
#读取/tmp/wechat/title文件中内容到变量Tit
Msg=$Date$Tit$(echo "$wx_msg")
rrdurl=$(echo "$wx_msg"|sed -n "/^http/p")
#截取graph_id网址
##################### 以下将报警图片下载至CA服务器进行保存,微信报警图片显示不了报警时间图片的情况#################
pic_url=$(echo "$wx_msg"|sed 's/\/graph.php/\/graph_image.php/g'|sed -n "/^http/p")
#获取替换url变量为图片下载路径
gid=$(echo "$pic_url" | sed -r 's/.*graph_id=(.*)&rra_id=.*/\1/')
gdate=_$(date +%Y_%m_%d_%H_%M_%S)
mkdir -p /var/www/html/wx_img/
pic_local=/var/www/html/wx_img/$gid$gdate.jpg
curl -o $pic_local $pic_url
#下面命令重置图片大小，适应企业号图文消息大小
convert -resize 480x275! $pic_local $pic_local
#下载报警图片到指定目录并以graph_id和日期命名
gurl=$(echo "$wx_msg"|sed -n "/^http/p"|awk -F "graph" '{print $1}')wx_img/$gid$gdate.jpg

#下载好的报警图片地址
if [ ! -n "$pic_url" ] ;then
#判断图片url是否存在
Pic=""
else
Pic=$gurl
fi

###########################################以下为微信报警接口文件#####################################################
GURL="https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$CropID&corpsecret=$Secret"
Gtoken=$(/usr/bin/curl -s -G $GURL | awk -F\" '{print $10}')
PURL="https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=$Gtoken"
function body() {
local int AppID=$WechatID

printf '{\n'
printf '\t"touser": "'"$UserID"\"",\n"
printf '\t"toparty": "'"$PartyID"\"",\n"
printf '\t"totag": "'"$TagID"\"",\n"
printf '\t"msgtype": "news",\n'
printf '\t"agentid": "'" $AppID "\"",\n"
printf '\t"news": {\n'
printf '\t"articles": [\n'
printf '{\n'
printf '\t\t"title": "'"$Tit"\","\n"
printf '\t\t"description": "'"$Msg"\","\n"
printf '\t\t"url": "'"$rrdurl"\","\n"
printf '\t\t"picurl": "'"$Pic"\","\n"
printf '\t}\n'
printf '\t]\n'
printf '\t}\n'
printf '}\n'
}
/usr/bin/curl --data-ascii "$(body )" $PURL

