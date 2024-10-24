const all_settings = window.wc.wcSettings.allSettings || {}

const payment_methods = all_settings.paymentMethodData
    ? all_settings.paymentMethodData.epoint_woocommerce_blocks_gateway
    : all_settings.epoint_woocommerce_blocks_gateway || {}

Object.entries(payment_methods).forEach(([key, value]) => {
    const label = value.title || "Ödeme Metodu"
    const logoUrl = value.logo_url

    const LabelComponent = () => {
        return window.wp.element.createElement(
            "div",
            {
                style: {
                    display: "flex",
                    alignItems: "center",
                    gap: "10px",
                },
            },
            window.wp.element.createElement("img", {
                src: logoUrl,
                alt: `${label} logosu`,
                style: { width: "130px", maxHeight: "49px", objectFit: "contain" },
            }),
            window.wp.element.createElement(
                "span",
                { style: { fontSize: "1.2em" } },
                key === "epoint" ? label : value.title
            )
        )
    }

    const DescriptionComponent = () => {
        const logoStyle =
            key === "epoint"
                ? { width: "150px", maxHeight: "75px", objectFit: "contain" }
                : { width: "100px", maxHeight: "50px", objectFit: "contain" }

        const descriptionImage = window.wp.element.createElement("img", {
            src: logoUrl,
            alt: `${label} logosu`,
            style: logoStyle,
        })

        return window.wp.element.createElement(
            "div",
            {
                style: {
                    display: "flex",
                    alignItems: "center",
                    gap: "20px",
                    marginBottom: "20px",
                    padding: "10px",
                    border: "1px solid #ccc",
                    borderRadius: "8px",
                    backgroundColor: "#f9f9f9",
                },
            },
            window.wp.element.createElement(
                "div",
                { style: { flex: 1 } },
                value.description || "Ödeme Metodu Açıklaması"
            ),
            key === "epoint" ? descriptionImage : null
        )
    }

    const Block_Gateway = {
        name: key,
        label: key === "epoint" ? label : window.wp.element.createElement(LabelComponent, null),
        content: window.wp.element.createElement(DescriptionComponent, null),
        edit: window.wp.element.createElement(DescriptionComponent, null),
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: value.supports,
        },
    }

    window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway)
})
