/*
 * Cordoba-first money helpers. Needs window.FX = {rate, buy} (set in header.php).
 * Prices are shown in C$; dollars are secondary information.
 */
(function (w) {
    var FX = w.FX = w.FX || { rate: 1, buy: 1 };

    function fmt(n, d) {
        n = parseFloat(n);
        if (isNaN(n)) { n = 0; }
        return n.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
    }

    FX.toCor = function (usd) { return (parseFloat(usd) || 0) * FX.rate; };
    FX.toUsd = function (cor) { return FX.rate > 0 ? (parseFloat(cor) || 0) / FX.rate : 0; };
    FX.cor = function (cor, d) { return 'C$ ' + fmt(cor, d === undefined ? 2 : d); };
    FX.usd = function (usd, d) { return '$' + fmt(usd, d === undefined ? 2 : d); };
    // amount given in dollars -> "C$ x ($y)"
    FX.pair = function (usd, d) { return FX.cor(FX.toCor(usd), d) + ' (' + FX.usd(usd, d) + ')'; };
    // amount given in cordobas -> "C$ x ($y)"
    FX.pairCor = function (cor, d) { return FX.cor(cor, d) + ' (' + FX.usd(FX.toUsd(cor), d) + ')'; };
})(window);
