umask 022

if [ -n "$BASH_VERSION" ]
then
    if [ -f "$HOME/.bashrc" ]
    then
        source "$HOME/.bashrc"
    fi
fi

if [ -n "$ZSH_VERSION" ]
then
    if [ -f "$HOME/.zshrc" ]
    then
        source "$HOME/.zshrc"
    fi
fi

if [ -d "$HOME/bin" ]
then
    PATH="$HOME/bin:$PATH"
fi
