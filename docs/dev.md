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
git branch -vr                   # 只看远程分支
git branch -va                   # 本地 + 远程分支


### 切换到 dev
git checkout dev

### 把远程 dev 的最新代码拉下来
git pull origin dev

### 开发完推送
git push origin dev


## 如何 把 dev 代码 合并到 main 中
git checkout main 			# 切换到main分支
git pull origin main       	# 先同步远程 main，避免冲突
git merge dev				# 合并 本地 dev 到 main
git push origin main 		#  推送到 远程 main

### 如果有重读
git add .
git commit -m "merge dev to main"                # 完成合并提交
git push origin main

### 合并完成后，如果 dev 不再需要，可以删除：
git branch -d dev              # 删除本地
git push origin --delete dev   # 删除远程


## 如果本地代码 改 乱套了。 要让本地完全和远程一致(丢弃所有本地修改和提交)

git fetch origin
git reset --hard origin/dev
git clean -fd

git pull origin dev


## 回退本地代码
### 查看与同步
同步远程仓库的标签信息
git fetch --tags origin

查看远程 main 分支的最近提交记录
git log -n 10 origin/main --oneline


### 强制回退本地到 d633522
git reset --hard d633522

### 强制推送到远程仓库
git push --force origin main