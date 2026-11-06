有远程的 origin/main，想再建一个 dev 分支
（并把这个分支也推到远程），按下面做就行。
推荐操作（最常用）

### 1. 确保本地 main 是最新的
git checkout main
git pull origin main

### 2. 从 main 创建并切换到 dev 分支
git checkout -b dev

### 3. 把 dev 分支推送到远程，并建立跟踪关系
git push -u origin dev 之后你就可以在 dev 分支上开发了。


## 常用后续 命令
### 查看当前所有分支（本地 + 远程）
git branch -va

### 切换到 dev
git checkout dev

### 把远程 dev 的最新代码拉下来
git pull origin dev

### 开发完推送
git push

