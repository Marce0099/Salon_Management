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

    FX.toUsd = function (cor) { return FX.rate > 0 ? (parseFloat(cor) || 0) / FX.rate : 0; };
    FX.cor = function (cor, d) { return 'C$ ' + fmt(cor, d === undefined ? 2 : d); };
    FX.usd = function (usd, d) {
        var n = parseFloat(usd) || 0;
        return (n < 0 ? '-' : '') + '$' + fmt(Math.abs(n), d === undefined ? 2 : d);
    };
    // amount in cordobas -> "C$ x", plus "($y)" only where dollars are allowed (FX.showUsd: /cash/* and /sales/register)
    FX.pair = function (cor, d) { return FX.cor(cor, d) + (FX.showUsd ? ' (' + FX.usd(FX.toUsd(cor), d) + ')' : ''); };
    FX.pairCor = FX.pair;
})(window);
