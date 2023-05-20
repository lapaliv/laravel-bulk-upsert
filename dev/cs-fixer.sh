#!/bin/sh

PHP_CS_FIXER="vendor/bin/php-cs-fixer"

# shellcheck disable=SC2039
if [[ ! -x ${PHP_CS_FIXER} ]]; then
    echo "PHP CodeSniffer bin not found or executable -> $PHP_CS_FIXER"
    exit 1
fi

CHANGED_FILES_FROM_GIT=$(git diff-index --cached --name-only --diff-filter=ACMR HEAD -- )
CHANGED_FILES=${CHANGED_FILES:-$CHANGED_FILES_FROM_GIT}

# shellcheck disable=SC2039
if [[ "$CHANGED_FILES" == "" ]]; then
    exit 0
fi

# match files against whitelist
FILES_TO_CHECK=""
for FILE in ${CHANGED_FILES}
do
    echo "$FILE" | egrep -q "$PHPCS_FILE_PATTERN"
    RETURN_VALUE=$?
    # shellcheck disable=SC2039
    if [[ "$RETURN_VALUE" -eq "0" ]]
    then
        FILES_TO_CHECK="$FILES_TO_CHECK $FILE"
    fi
done

# shellcheck disable=SC2039
if [[ "$FILES_TO_CHECK" == "" ]]; then
    exit 0
fi

FLAGS="--config=.php-cs-fixer.dist.php"

# execute the code sniffer
OUTPUT=$(${PHP_CS_FIXER} fix ${FLAGS} --verbose ${FILES_TO_CHECK})
RETURN_VALUE=$?

# shellcheck disable=SC2039
if [[ ${RETURN_VALUE} -ne 0 ]]; then
    echo "$OUTPUT" | less
fi

# execute the code sniffer
OUTPUT=$(${PHP_CS_FIXER} fix ${FLAGS} ${FILES_TO_CHECK} && git add ${FILES_TO_CHECK})
RETURN_VALUE=$?

# shellcheck disable=SC2039
if [[ ${RETURN_VALUE} -ne 0 ]]; then
    echo "$OUTPUT" | less
fi
