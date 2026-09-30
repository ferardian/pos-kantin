export const appRoute = (path) => {
    if (!path) return '';
    if (path.startsWith('http') || path.startsWith('mailto:') || path.startsWith('tel:')) {
        return path;
    }
    
    const isSubpath = typeof window !== 'undefined' 
        ? window.location.pathname.startsWith('/pos-kantin')
        : false;

    const cleanPath = path.startsWith('/') ? path : '/' + path;
    
    if (isSubpath) {
        if (cleanPath.startsWith('/pos-kantin')) {
            return cleanPath;
        }
        return '/pos-kantin' + cleanPath;
    }

    return cleanPath;
};
