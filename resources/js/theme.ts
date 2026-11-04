import type { ThemeConfig } from 'ant-design-vue/es/config-provider/context';

/** Paleta tomada del logo VENTRACK. */
export const palette = {
    navy: '#0A1F44', // "VEN"
    blue: '#0461D8', // "TRACK" y arco del pin
    teal: '#1597A5', // degradado azul → verde
    green: '#2DCB6A', // flecha y barras
    greenDark: '#16804B', // base de las barras
    amber: '#F0A202',
    red: '#D9363E',
    purple: '#6E4BC2',
    bg: '#F3F7FA',
    line: '#E1E8F0',
    muted: '#5D7085',
};

export const gradient = `linear-gradient(90deg, ${palette.blue} 0%, ${palette.teal} 55%, ${palette.green} 100%)`;

export const theme: ThemeConfig = {
    token: {
        colorPrimary: palette.blue,
        colorInfo: palette.blue,
        colorSuccess: '#1FAF5C',
        colorWarning: palette.amber,
        colorError: palette.red,
        colorLink: palette.blue,
        colorTextBase: palette.navy,
        colorBgLayout: palette.bg,
        colorBorderSecondary: palette.line,
        borderRadius: 10,
        fontFamily: "Figtree, system-ui, -apple-system, 'Segoe UI', sans-serif",
        fontSize: 14,
        controlHeight: 40,
        controlHeightSM: 32,
        controlHeightLG: 48,
        fontSizeLG: 15,
    },
    components: {
        Layout: { colorBgHeader: '#ffffff', colorBgBody: palette.bg },
        Menu: { colorItemBgSelected: '#E6F0FD', colorItemTextSelected: palette.blue, radiusItem: 10, itemMarginInline: 10 },
    },
};
