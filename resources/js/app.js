/**
 * Save the delegate ID card on the dashboard as a PNG.
 */
window.hbDownloadCard = async (node, filename) => {
    const { toPng } = await import('html-to-image');
    const dataUrl = await toPng(node, { pixelRatio: 3, cacheBust: true });
    const link = document.createElement('a');
    link.download = filename;
    link.href = dataUrl;
    link.click();
};
