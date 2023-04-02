git-hooks:
	cp dev/pre-commit .git/hooks/pre-commit && chmod ug+x .git/hooks/pre-commit
	cp dev/cs-fixer.sh .git/hooks/ && chmod ug+x .git/hooks/cs-fixer.sh
