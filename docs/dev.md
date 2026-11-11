# 有远程的 origin/main，想再建一个 dev 分支
（并把这个分支也推到远程），按下面做就行。
推荐操作（最常用）

### 1. 确保本地 main 是最新的
git checkout main
git pull origin main

### 2. 从 main 创建并切换到 dev 分支
git checkout -b dev

### 3. 把 dev 分支推送到远程，并建立跟踪关系
git push -u origin dev 之后你就可以在 dev 分支上开发了。


# 常用后续 命令
### 查看当前所有分支（本地 + 远程）
git branch -vr                   # 只看远程分支
git branch -va                   # 本地 + 远程分支


### 切换到 dev
git checkout dev

### 把远程 dev 的最新代码拉下来
git pull origin dev

### 开发完推送
git push origin dev


# 如何 把 dev 代码 合并到 main 中
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


# 回退本地代码
### 查看与同步
同步远程仓库的标签信息
git fetch --tags origin

查看远程 main 分支的最近提交记录
git log -n 10 origin/main --oneline


### 强制回退本地到 d633522
git reset --hard d633522

### 强制推送到远程仓库
git push --force origin main


# 发布与重新发布

## 第一步：删除旧的 Tag（远程 + 本地）

### 删除远程仓库的 v0.0.3 标签
git push origin --delete v0.0.3

### 删除本地仓库的 v0.0.3 标签
git tag -d v0.0.3


## 第二步：处理平台上的 Release（如有）

如果 v0.0.3 在网页端（GitHub / GitLab / Gitee）关联了 Release（发行版），删除 Tag 后该 Release 通常会变成 Draft（草稿）或残留状态。
• 网页端清理： 进入仓库主页的 Releases 页面，找到 v0.0.3 对应的条目，点击删除（Delete）。
• 注意： 如果你的仓库配置了 GitHub Actions 等自动化流水线，删除旧 Release 可以避免新 Release 触发时出现资源重名冲突。

## 第三步：提交最新代码并重新发布 v0.0.3

确保你修改好的代码已经正常 commit 并 push 到远程分支后，执行以下操作：
### 在当前最新提交上重新创建标签
git tag -a v0.0.3 -m "重新发布 v0.0.3 版本"

(注：如果不需要附注信息，也可以直接用轻量标签 git tag v0.0.3)

### 将新标签推送到远程仓库
git push origin v0.0.3

## 提醒（团队协作）
如果其他协作者之前已经拉取过旧的 v0.0.3 标签，他们的本地 Git 不会自动覆盖同名标签。需要通知他们执行以下命令同步最新的标签：
###  同步最新标签
git fetch --prune --prune-tags