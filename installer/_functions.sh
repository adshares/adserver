#!/dev/null
set -e

test ${_FUNCTIONS_FILE_WAS_LOADED:-0} -eq 1 && echo "Functions file was already loaded" >&2 && exit 127
_FUNCTIONS_FILE_WAS_LOADED=1

# read_option opt_name, prompt, prefill, maxlength
read_option () {
    local PREV
    local REPLY
    eval $( echo PREV=\$${1} )
    local MAXLENGTH=${4:-0}

    if [ $3 -gt 0 ]
    then
        read -e -p "${2}: " -i "${PREV}" -n $MAXLENGTH ${1}
    else
        read -e -p "${2} [$PREV]: " -n $MAXLENGTH REPLY
        if [ ! -z $REPLY ]
        then
            eval $( echo ${1}=\$REPLY )
        fi
    fi
}

# save current env to file based on template
# save_env (template, output file)
save_env () {
    test ! -e $1 && echo "Environment template ($1) not found." && return 1
    test -e $2 && rm $2
    local EXPORT=$(export -p)

    echo "Preparing ($2) environment file."

    while read i
    do
        echo -n $i= >> $2
        echo "$EXPORT" | grep $i= | head -n1 | awk 'NF { st = index($0,"=");printf("%s", substr($0,st+1)) }' >> $2
        echo "" >> $2
    done < <(cat $1 | awk -F"=" 'NF {print $1}')
}

# read dotenv file and export vars. Does not overwrite existing vars
# read_env(.env file)
read_env() {
    if [ ! -e $1 ]
    then
        echo "Environment file ($1) not found."
        return 1
    fi
    source <(grep -v '^#' $1 | sed -E 's|^([^=]+)=(.*)$|: ${\1=\2}; export \1|g')
}

VENDOR_NAME=${VENDOR_NAME:-"adshares"}
VENDOR_USER=${VENDOR_USER:-"${VENDOR_NAME}"}
VENDOR_DIR=${VENDOR_DIR:-"/opt/${VENDOR_NAME}"}

BACKUP_DIR=${BACKUP_DIR:-"${VENDOR_DIR}/.backup"}
DATA_DIR=${DATA_DIR:-"${VENDOR_DIR}/.data"}
LOG_DIR=${LOG_DIR:-"/var/log/${VENDOR_NAME}"}
RUN_DIR=${RUN_DIR:-"/var/run/${VENDOR_NAME}"}

SCRIPT_DIR=${SCRIPT_DIR:-"${VENDOR_DIR}/.script"}

SERVICE_NAME=${SERVICE_NAME:-$(basename ${PWD})}
if [[ "$SERVICE_NAME" == "root" || "$SERVICE_NAME" == "$VENDOR_NAME" ]]
then
    SERVICE_NAME=`basename ${SCRIPT_DIR}`
fi

SERVICE_DIR="$VENDOR_DIR/$SERVICE_NAME"

#===

_REQUIRED_USER_TYPE=${1:-"regular"}

if [[ "$_REQUIRED_USER_TYPE" != "any" ]]
then
    if [[ "$_REQUIRED_USER_TYPE" == "root" ]]
    then
        if [[ $EUID -ne 0 ]]
        then
            echo "You need to be root to run $0" >&2
            exit 1
        fi
    elif [[ "$_REQUIRED_USER_TYPE" != "root" ]]
    then
        if  [[ $EUID -eq 0 ]]
        then
            echo "You cannot be root to run $0" >&2
            exit 2
        elif [[ `id --user --name` != "$VENDOR_USER" ]]
        then
            echo "You need to be $VENDOR_USER to run $0" >&2
            id
            exit 3
        fi
    fi
fi

unset _REQUIRED_USER_TYPE

#===

set -u

if [[ ${DEBUG_MODE:-0} -eq 1 ]]
then
    echo ""
    echo "# ==="
    echo "#"
    echo "# $0 $*"
    echo "#"
    echo "# --- #"
    echo "# `id`"
    echo "# SERVICE_NAME: $SERVICE_NAME"
    echo "#  SERVICE_DIR: $SERVICE_DIR"
    echo "#"
    echo "# VENDOR_NAME=$VENDOR_NAME"
    echo "# VENDOR_USER=$VENDOR_USER"
    echo "#  VENDOR_DIR=$VENDOR_DIR"
    echo "#"
    echo "# BACKUP_DIR=$BACKUP_DIR"
    echo "#   DATA_DIR=$DATA_DIR"
    echo "#    LOG_DIR=$LOG_DIR"
    echo "#    RUN_DIR=$RUN_DIR"
    echo "#"
    echo "# SCRIPT_DIR=$SCRIPT_DIR"
    echo "#        PWD=$PWD"
    echo "# --- #"
fi
