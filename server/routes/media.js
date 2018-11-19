const express = require('express')
const router = express.Router()
const Media = require('../database/models/media')

router.post('/', (req, res) => {
    console.log('media added');

    const { url, title, artists, description, platform } = req.body
    // ADD VALIDATION
    Media.findOne({ url: url }, (err, media) => {
        if (err) {
            console.log('User.js post error: ', err)
        } else if (media) {
            res.json({
                error: `Sorry, already a song with the url: ${media}`
            })
        }
        else {
            const newMedia = new Media({
							url: url,
							title: title,
							artists: artists,
							description: description,
							platform, platform
            })
            newMedia.save((err, savedMedia) => {
                if (err) return res.json(err)
                res.json(savedMedia)
            })
        }
    })
})


router.get('/', (req, res, next) => {
    console.log('===== media!!======')
    console.log(req.media)

    Media.find((err, media) => {
        if (err) {
            console.log('User.js post error: ', err)
        } else {
            console.log(media)
            res.json({ media: media })
        }
    })
})


module.exports = router