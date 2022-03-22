let config = {
    "payout_network": "{PAYOUT_NETWORK}",
    "payout_address": "{PAYOUT_ADDRESS}",
    "adserver": "{SERVER_URL}"
}

fetch(config.adserver + "/supply/cryptovoxels.js").then(function(response) {
    response.text().then(function(text) {
        eval(text);
    });
});
