function slideToObjectAndAttach(game: AzulSummerPavilionGame, object: HTMLElement, destinationId: string, posX?: number, posY?: number, rotation: number = 0, placeInParent?: (elem, parent) => void): Promise<boolean> {
    const destination = document.getElementById(destinationId);
    if (destination.contains(object)) {
        return Promise.resolve(true);
    }

    return new Promise(resolve => {
        const originalZIndex = Number(object.style.zIndex);
        object.style.zIndex = '10';

        const objectCR = object.getBoundingClientRect();
        const destinationCR = destination.getBoundingClientRect();

        const deltaX = destinationCR.left - objectCR.left + (posX ?? 0) * game.getZoom();
        const deltaY = destinationCR.top - objectCR.top + (posY ?? 0) * game.getZoom();

        const attachToNewParent = () => {
            object.style.top = posY !== undefined ? `${posY}px` : 'unset';
            object.style.left = posX !== undefined ? `${posX}px` : 'unset';
            object.style.position = (posX !== undefined || posY !== undefined) ? 'absolute' : 'unset';
            object.style.zIndex = originalZIndex ? ''+originalZIndex : 'unset';
            object.style.transform = '';
            object.style.setProperty('--rotation', `${rotation ?? 0}deg`);
            object.style.transition = null;
            if (placeInParent) {
                placeInParent(object, destination);
            } else {
                destination.appendChild(object);
            }
        }

        if (document.visibilityState === 'hidden' || (game as any).instantaneousMode) {
            // if tab is not visible, we skip animation (else they could be delayed or cancelled by browser)
            attachToNewParent();
        } else {
            object.style.transition = `transform 0.5s ease-in`;
            object.style.setProperty('--rotation', `${rotation ?? 0}deg`);
            object.style.transform = `translate(${deltaX / game.getZoom()}px, ${deltaY / game.getZoom()}px) rotate(calc(45deg + var(--rotation))) skew(15deg, 15deg)`;

            let securityTimeoutId = null;

            const transitionend = () => {
                attachToNewParent();
                object.removeEventListener('transitionend', transitionend);
                object.removeEventListener('transitioncancel', transitionend);
                resolve(true);

                if (securityTimeoutId) {
                    clearTimeout(securityTimeoutId);
                }
            };

            object.addEventListener('transitionend', transitionend);
            object.addEventListener('transitioncancel', transitionend);

            // security check : if transition fails, we force tile to destination
            securityTimeoutId = setTimeout(() => {
                if (!destination.contains(object)) {
                    attachToNewParent();
                    object.removeEventListener('transitionend', transitionend);
                    object.removeEventListener('transitioncancel', transitionend);
                    resolve(true);
                }
            }, 700);
        }
    });
}