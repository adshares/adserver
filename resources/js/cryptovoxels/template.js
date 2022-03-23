/*
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

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
