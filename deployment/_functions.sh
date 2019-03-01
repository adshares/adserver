#!/dev/null

if [[ ${_FUNCTIONS_FILE_WAS_LOADED:-0} -eq 1 ]]
then
    echo "Functions file was already loaded" &>2
    exit 127
fi

VENDOR_NAME=${VENDOR_NAME:-adshares}

if [ -z $DO_INSTALL ]
then
    if [[ $EUID -eq 0 ]]
    then
        echo "Don't be root when running $0" >&2
        exit 1
    fi


    if [[ "$VENDOR_NAME" != `id --user --name` ]]
    then
        echo "You need to be $VENDOR_NAME to run $0" >&2
        #exit 1
    fi
fi
set -e

INSTALLATION_DIR=${INSTALLATION_DIR:-/opt/${VENDOR_NAME}}

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
#  save_env (template, output file)
save_env () {
    test -e $2 && rm $2
	local EXPORT=$(export -p)
    while read i; do
	    echo -n $i= >> $2
	    echo "$EXPORT" | grep $i= | head -n1 | awk 'NF { st = index($0,"=");printf("%s", substr($0,st+1)) }' >> $2
	    echo "" >> $2
    done < <(cat $1 | awk -F"=" 'NF {print $1}')
}

# read dotenv file and export vars. Does not overwrite existing vars
# read_env(.env file)
read_env() {
    if [ ! -e $1 ] ; then return 1; fi
    source <(grep -v '^#' $1 | sed -E 's|^(.+)=(.*)$|: ${\1=\2}; export \1|g')
}