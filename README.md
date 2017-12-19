
#### 功能介绍
1.本脚本自动安装cacti1.1.X版本

2.自动安装cacti1.1.X、rrdtool1.7.0、spine1.1.X到系统

3.本脚本运行在centos7.3下

4.本脚本自动添加中文微软雅黑字体到centos系统中,rrdtool及cacti默认支持中文

5.本脚本开头有自定义rrdtool水印变量,可根据需求更改

6.本脚本自动添加图形导出脚本,自动按照日期每日、每天导出图形树内所有图形和数据

7.本脚本自动添加数据库备份脚本

8.本脚本自动下载目前已验证可以正常使用的cacti1.1.X版本下的插件

9.本脚本自动更改graph_xport.php文件编码,解决中文标题图形导出数据的乱码问题

10.本脚本自动修改某些常用settings设置项

---

#### 使用方法

```git clone https://github.com/babyfenei/cacti-autoinstall.git```
 
```cd cacti-autoinstall && bash start.sh```

---
 
#### 备注
因国内墙的问题，脚本运行过程中可能会出现下载文件超时导致脚本运行失败。出现此问题时请查看脚本出错步骤的log信息，修改脚本start.sh中出错log步骤前面的运行项重新运行。
