export default class Node {

    constructor(dom) {
        this._dom = dom;
        this._node = null;
    }

    _fetch() { }

    _createNode() { }

    _find(name, stringType = true) {
        let tag = Array.from(this._dom.getElementsByTagName('*')).find(n => n.localName.toLowerCase() === name.toLowerCase());
        if (tag) {
            let value = tag.textContent;
            if (stringType)
                return value;
            else
                return parseInt(value);
        }
        return null;
    }

    _createSimpleItem(value, className) {
        let li = document.createElement('li');
        let valueSpan = document.createElement('span');

        li.className = `list-group-item ${className}`;
        valueSpan.className = 'value';
        valueSpan.innerText = value;
        li.appendChild(valueSpan);
        return li;
    }

    _createBadgeItem(key, value, className, trustedIcons = false) {
        let li = document.createElement('li');
        let keySpan = document.createElement('span');
        let valueSpan = document.createElement('span');

        li.className = `list-group-item d-flex justify-content-between align-items-center ${className}`;
        keySpan.className = 'key';
        valueSpan.className = 'badge bg-primary rounded-pill value';
        keySpan.innerText = key;
        if (trustedIcons) valueSpan.innerHTML = value;
        else valueSpan.textContent = value;
        li.appendChild(keySpan);
        li.appendChild(valueSpan);
        return li;
    }

    _createLinkItem(key, value, className) {
        let a = document.createElement('a');
        if (/^https?:\/\//i.test(value)) a.href = value;
        a.rel = 'noopener noreferrer';
        a.innerText = key;
        a.target = '_blank';
        a.className = `list-group-item list-group-item-action ${className}`;
        return a;
    }

    request(url, callBack, postData = null) {
        let xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState === 4) {
                const dom = new DOMParser().parseFromString(this.responseText, 'application/xml');
                if (!dom.querySelector('parsererror')) callBack(dom);
            }
        };
        if (postData) {
            xhttp.open('POST', url, true);
            xhttp.send(postData);
        } else {
            xhttp.open('GET', url, true);
            xhttp.send();
        }
    }

    removeChilds(parent) {
        while (parent.firstChild) {
            parent.removeChild(parent.firstChild);
        }
    }

    get node() {
        return this._node;
    }
}